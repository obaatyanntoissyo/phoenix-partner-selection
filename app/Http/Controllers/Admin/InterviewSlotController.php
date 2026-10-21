<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\InterviewBooking;
use App\Models\InterviewSlot;
use App\Services\GoogleCalendar;
use App\Services\InterviewBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** 面談の空き枠と予約の管理、Googleカレンダー連携の状態表示。 */
class InterviewSlotController extends Controller
{
    private const MAX_SLOTS_PER_REQUEST = 200;

    public function __construct(private InterviewBookingService $bookings, private GoogleCalendar $calendar) {}

    public function index(): View
    {
        return view('admin.slots', [
            'slots' => InterviewSlot::query()->where('starts_at', '>=', today()->subDays(7))
                ->with('activeBooking.application')->orderBy('starts_at')->limit(500)->get(),
            'problems' => InterviewBooking::query()->whereIn('calendar_state', ['dead'])->orWhere(fn ($q) => $q->where('calendar_state', 'pending')->where('calendar_attempts', '>', 0))
                ->with(['application', 'slot'])->latest('id')->limit(50)->get(),
            'cancelled' => InterviewBooking::query()->where('status', 'cancelled')->with(['application', 'slot'])->latest('cancelled_at')->limit(20)->get(),
            'google' => [
                'client' => $this->calendar->hasClient(),
                'connected' => $this->calendar->isConnected(),
                'account' => AppSetting::get('google_account'),
                'redirect_uri' => route('admin.google.callback'),
            ],
            'mode' => AppSetting::get('booking_mode', 'form'),
            'settings' => AppSetting::query()->whereIn('key', ['booking_lead_hours', 'google_calendar_id', 'google_meet'])->pluck('value', 'key'),
        ]);
    }

    /** 期間・曜日・時間帯・1枠の長さから空き枠をまとめて作る。同じ開始時刻の枠は作らない。 */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:'.today()->addDays(180)->format('Y-m-d')],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'time_from' => ['required', 'date_format:H:i'],
            'time_to' => ['required', 'date_format:H:i', 'after:time_from'],
            'duration' => ['required', 'integer', 'in:15,30,45,60,90,120'],
            'gap' => ['nullable', 'integer', 'in:0,10,15,30,60'],
        ], [
            'weekdays.required' => '曜日を1つ以上選んでください。',
            'time_to.after' => '終了時刻は開始時刻より後にしてください。',
            'date_to.before_or_equal' => '作成できるのは180日先までです。',
        ]);

        $starts = [];
        $step = (int) $data['duration'] + (int) ($data['gap'] ?? 0);
        for ($day = Carbon::parse($data['date_from']); $day->lte(Carbon::parse($data['date_to'])); $day->addDay()) {
            if (! in_array($day->dayOfWeek, array_map('intval', $data['weekdays']), true)) {
                continue;
            }
            $end = $day->copy()->setTimeFromTimeString($data['time_to']);
            for ($t = $day->copy()->setTimeFromTimeString($data['time_from']); $t->copy()->addMinutes((int) $data['duration'])->lte($end); $t->addMinutes($step)) {
                if ($t->isFuture()) {
                    $starts[] = $t->copy();
                }
            }
        }
        if (! $starts) {
            return back()->withInput()->withErrors(['date_from' => '条件に合う枠がありません（過去の時刻は作成しません）。']);
        }
        if (count($starts) > self::MAX_SLOTS_PER_REQUEST) {
            return back()->withInput()->withErrors(['date_from' => '一度に作成できるのは'.self::MAX_SLOTS_PER_REQUEST.'枠までです。期間を短くしてください。']);
        }

        $now = now();
        $created = DB::table('interview_slots')->insertOrIgnore(array_map(fn (Carbon $s) => [
            'starts_at' => $s->toDateTimeString(),
            'ends_at' => $s->copy()->addMinutes((int) $data['duration'])->toDateTimeString(),
            'created_by' => $request->user()->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $starts));
        AuditLog::record('slots_create', null, null, ['count' => $created, 'from' => $data['date_from'], 'to' => $data['date_to']]);

        return back()->with('status', "{$created}件の空き枠を作成しました".($created < count($starts) ? '（同じ時刻の枠がすでにあるものは作成していません）' : '').'。');
    }

    public function destroy(InterviewSlot $slot): RedirectResponse
    {
        $deleted = DB::transaction(function () use ($slot) {
            $locked = InterviewSlot::query()->whereKey($slot->id)->lockForUpdate()->first();
            if (! $locked || InterviewBooking::query()->where('active_slot_id', $slot->id)->exists()) {
                return false;
            }
            AuditLog::record('slot_delete', $locked, null, ['starts_at' => $locked->starts_at->toDateTimeString()]);
            $locked->delete();

            return true;
        });

        return $deleted
            ? back()->with('status', '空き枠を削除しました。')
            : back()->withErrors(['slot' => '予約が入っている枠は削除できません。先に予約を取り消してください。']);
    }

    public function cancel(Request $request, InterviewBooking $booking): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'notify' => ['nullable', 'boolean'],
        ], ['reason.required' => '取り消しの理由を入力してください。']);
        if (! $booking->isActive()) {
            return back()->withErrors(['booking' => 'この予約はすでに取り消されています。']);
        }
        $this->bookings->cancel($booking, (int) $request->user()->id, $data['reason'], (bool) ($data['notify'] ?? false));

        return back()->with('status', '予約を取り消しました。枠は空きに戻り、応募者は別の日時を選び直せます。');
    }

    public function retry(InterviewBooking $booking): RedirectResponse
    {
        $this->bookings->retry($booking);
        $booking->refresh();

        return $booking->calendar_state === 'synced' || $booking->calendar_state === 'deleted'
            ? back()->with('status', 'Googleカレンダーに反映しました。')
            : back()->withErrors(['booking' => 'Googleカレンダーに反映できませんでした：'.$booking->calendar_error]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_lead_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'google_calendar_id' => ['required', 'string', 'max:255', 'regex:/^[^\s\/?#]+$/'],
            'google_meet' => ['nullable', 'boolean'],
        ], ['google_calendar_id.regex' => 'カレンダーIDの形式が正しくありません（通常は primary またはメールアドレス形式）。']);
        $data['google_meet'] = ! empty($data['google_meet']) ? '1' : '0';
        foreach ($data as $key => $value) {
            AppSetting::put($key, (string) $value, $request->user()->id);
        }
        AuditLog::record('settings', null, null, ['changed' => array_keys($data)]);

        return back()->with('status', '予約の設定を保存しました。');
    }
}
