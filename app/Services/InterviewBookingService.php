<?php

namespace App\Services;

use App\Exceptions\ApplicantFlowException;
use App\Models\Application;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Interview;
use App\Models\InterviewBooking;
use App\Models\InterviewSlot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * このシステム内での面談予約。予約の確定は DB だけで行い、Google カレンダーへの登録は後から
 * （直後に1回試し、失敗したら partner:sync-calendar が再試行）行う。Google の障害で予約を失敗にしない。
 */
class InterviewBookingService
{
    public const MAX_CALENDAR_ATTEMPTS = 5;

    public function __construct(private GoogleCalendar $calendar, private MailQueue $mail) {}

    /** 応募者に見せる空き枠。締切（何時間前まで）を過ぎた枠・予約済みの枠・カレンダーで予定ありの時間帯を除く。 */
    public function availableSlots(): Collection
    {
        $slots = InterviewSlot::query()
            ->where('starts_at', '>=', $this->earliestStart())
            ->whereDoesntHave('activeBooking')
            ->orderBy('starts_at')
            ->limit(200)
            ->get();

        return $this->withoutBusy($slots);
    }

    /** 枠を予約する。権限・締切・空きをロックの中で確かめ、二重予約は一意制約でも防ぐ。 */
    public function book(Application $application, int $slotId): InterviewBooking
    {
        $slot = InterviewSlot::query()->find($slotId);
        if (! $slot || $this->withoutBusy(collect([$slot]))->isEmpty()) {
            throw new ApplicantFlowException('slot_taken');
        }

        try {
            $booking = DB::transaction(function () use ($application, $slotId) {
                $interview = Interview::query()->where('application_id', $application->id)->lockForUpdate()->first();
                if (! $interview?->access_granted) {
                    throw new ApplicantFlowException('forbidden');
                }
                if (InterviewBooking::query()->where('active_application_id', $application->id)->exists()) {
                    throw new ApplicantFlowException('already_booked');
                }
                $slot = InterviewSlot::query()->whereKey($slotId)->lockForUpdate()->first();
                if (! $slot || $slot->starts_at->lt($this->earliestStart()) || $slot->activeBooking()->exists()) {
                    throw new ApplicantFlowException('slot_taken');
                }

                $connected = $this->calendar->isConnected();
                $booking = InterviewBooking::create([
                    'slot_id' => $slot->id,
                    'application_id' => $application->id,
                    'status' => 'active',
                    'active_slot_id' => $slot->id,
                    'active_application_id' => $application->id,
                    // base32hex（0-9a-v）の範囲に収まる16進数で、推測できない予定IDを作る
                    'calendar_event_id' => 'pps'.bin2hex(random_bytes(12)),
                    'calendar_state' => $connected ? 'pending' : 'skipped',
                    'calendar_next_attempt_at' => $connected ? now() : null,
                ]);
                $interview->update([
                    'booking_status' => 'confirmed',
                    'stage' => 'booked',
                    'interview_at' => $slot->starts_at,
                ]);
                AuditLog::record('booking', $application, null, ['slot_id' => $slot->id, 'starts_at' => $slot->starts_at->toDateTimeString()]);

                $vars = ['name' => $application->name, 'number' => $application->number, 'interview_at' => $slot->label()];
                $this->mail->enqueue('p1_booking_confirmed', $application, [$application->email], $vars, 'booking:'.$booking->id.':applicant');
                $this->mail->enqueue('admin_booking', $application, AppSetting::notifyEmails(), $vars + ['admin_url' => route('admin.applications.show', $application)], 'booking:'.$booking->id.':admin');

                return $booking;
            });
        } catch (QueryException $e) {
            // 同時に同じ枠・同じ応募者の予約が入った場合（一意制約）
            if ($this->isDuplicate($e)) {
                throw new ApplicantFlowException('slot_taken');
            }
            throw $e;
        }

        $this->trySync($booking);

        return $booking->refresh();
    }

    /** 管理者による予約の取り消し。枠は空きに戻り、Google の予定は削除する（招待者へ取り消しが通知される）。 */
    public function cancel(InterviewBooking $booking, int $adminId, string $reason, bool $notifyApplicant): void
    {
        DB::transaction(function () use ($booking, $adminId, $reason, $notifyApplicant) {
            $booking = InterviewBooking::query()->whereKey($booking->id)->lockForUpdate()->first();
            if (! $booking->isActive()) {
                return;
            }
            $hasEvent = in_array($booking->calendar_state, ['pending', 'synced', 'dead'], true);
            $booking->update([
                'status' => 'cancelled',
                'active_slot_id' => null,
                'active_application_id' => null,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
                'cancelled_by' => $adminId,
                'calendar_state' => $hasEvent ? 'pending' : $booking->calendar_state,
                'calendar_attempts' => $hasEvent ? 0 : $booking->calendar_attempts,
                'calendar_next_attempt_at' => $hasEvent ? now() : null,
            ]);
            Interview::query()->where('application_id', $booking->application_id)->update([
                'booking_status' => 'available',
                'stage' => 'unbooked',
                'interview_at' => null,
            ]);
            $application = $booking->application;
            AuditLog::record('cancel_booking', $application, $reason, ['slot_id' => $booking->slot_id]);
            if ($notifyApplicant) {
                $this->mail->enqueue('p1_booking_cancelled', $application, [$application->email], [
                    'name' => $application->name, 'number' => $application->number, 'interview_at' => $booking->slot->label(),
                ], 'booking:'.$booking->id.':cancelled', $adminId);
            }
        });

        $this->trySync($booking->refresh());
    }

    /** 登録待ち・再試行待ちを処理し、処理件数を返す。 */
    public function processDue(int $limit = 20): int
    {
        $processed = 0;
        for ($i = 0; $i < $limit; $i++) {
            $booking = DB::transaction(function () {
                $booking = InterviewBooking::query()
                    ->where('calendar_state', 'pending')
                    ->where('calendar_next_attempt_at', '<=', now())
                    ->orderBy('id')
                    ->lock('for update skip locked')
                    ->first();
                $booking?->update(['calendar_next_attempt_at' => now()->addMinutes(10)]);

                return $booking;
            });
            if (! $booking) {
                break;
            }
            $this->sync($booking);
            $processed++;
        }

        return $processed;
    }

    /** 管理画面からの再試行（失敗が続いた予約を登録待ちに戻す）。 */
    public function retry(InterviewBooking $booking): void
    {
        if ($booking->calendar_state === 'dead' || $booking->calendar_state === 'skipped') {
            $booking->update(['calendar_state' => 'pending', 'calendar_attempts' => 0, 'calendar_next_attempt_at' => now(), 'calendar_error' => null]);
        }
        $this->trySync($booking->refresh());
    }

    private function trySync(InterviewBooking $booking): void
    {
        if ($booking->calendar_state !== 'pending') {
            return;
        }
        $claimed = InterviewBooking::query()->whereKey($booking->id)->where('calendar_state', 'pending')
            ->where('calendar_next_attempt_at', '<=', now())
            ->update(['calendar_next_attempt_at' => now()->addMinutes(10)]);
        if ($claimed) {
            $this->sync($booking->refresh());
        }
    }

    private function sync(InterviewBooking $booking): void
    {
        $booking->calendar_attempts++;
        try {
            if ($booking->isActive()) {
                $application = $booking->application;
                $slot = $booking->slot;
                $result = $this->calendar->insertEvent(
                    $booking->calendar_event_id,
                    '【面談】一括制作パートナー '.$application->name.' 様（'.$application->number.'）',
                    "応募番号：{$application->number}\n管理画面：".route('admin.applications.show', $application),
                    $slot->starts_at,
                    $slot->ends_at,
                    $application->email,
                    AppSetting::get('google_meet', '1') === '1',
                );
                // 画面にリンクとして出すため、https の URL だけを保存する
                $booking->meet_url = is_string($result['meet_url']) && str_starts_with($result['meet_url'], 'https://') ? mb_substr($result['meet_url'], 0, 255) : null;
                $booking->calendar_state = 'synced';
            } else {
                $this->calendar->deleteEvent($booking->calendar_event_id);
                $booking->calendar_state = 'deleted';
            }
            $booking->calendar_next_attempt_at = null;
            $booking->calendar_error = null;
            $booking->save();
        } catch (GoogleCalendarException $e) {
            $booking->calendar_error = mb_substr($e->getMessage(), 0, 480);
            if ($booking->calendar_attempts >= self::MAX_CALENDAR_ATTEMPTS) {
                $booking->calendar_state = 'dead';
                $booking->calendar_next_attempt_at = null;
            } else {
                $booking->calendar_next_attempt_at = now()->addMinutes(2 ** $booking->calendar_attempts);
            }
            $booking->save();
            Log::warning('calendar sync failed', ['booking_id' => $booking->id, 'attempts' => $booking->calendar_attempts]);
            if ($booking->calendar_state === 'dead') {
                app(AdminAlert::class)->raise('Googleカレンダーへの登録失敗（再試行上限）', $booking->application, 'calendar-dead:'.$booking->id.':'.$booking->status);
            }
        }
    }

    /** カレンダーで予定が入っている時間帯と重なる枠を除く。Google に接続できないときは除外せずに続ける。 */
    private function withoutBusy(Collection $slots): Collection
    {
        if ($slots->isEmpty() || ! $this->calendar->isConnected()) {
            return $slots;
        }
        try {
            $busy = $this->calendar->busy($slots->min('starts_at'), $slots->max('ends_at'));
        } catch (GoogleCalendarException $e) {
            Log::warning('calendar freebusy failed', ['error' => $e->getMessage()]);

            return $slots;
        }

        return $slots->reject(function (InterviewSlot $slot) use ($busy) {
            foreach ($busy as [$start, $end]) {
                if ($slot->starts_at->lt($end) && $slot->ends_at->gt($start)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private function earliestStart(): Carbon
    {
        return now()->addHours(max(0, (int) AppSetting::get('booking_lead_hours', '24')));
    }

    private function isDuplicate(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE');
    }
}
