<?php

namespace App\Http\Controllers\Applicant;

use App\Exceptions\ApplicantFlowException;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AppSetting;
use App\Services\AdminAlert;
use App\Services\InterviewBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 面談予約への案内。外部予約URLはサーバー側で通過状態と本人セッションを確かめたときだけ返す。
 * 外部ページへ移動しても「予約完了」とはせず、移動の記録だけを残す。
 */
class BookingController extends Controller
{
    public function __construct(private InterviewBookingService $bookings) {}

    public function show(Request $request): View
    {
        $application = $this->authorizeBooking($request);
        $mode = AppSetting::get('booking_mode', 'form');
        if ($mode === 'slots') {
            $booking = $application->activeBooking;

            return view('my.slots', [
                'application' => $application,
                'booking' => $booking,
                'slots' => $booking ? collect() : $this->bookings->availableSlots(),
            ]);
        }

        return view('my.booking', [
            'application' => $application,
            'mode' => $mode,
            'interview' => $application->interview,
        ]);
    }

    /** このシステム内の空き枠から予約する（予約先の種類が「このシステムで空き枠を選ぶ」のとき）。 */
    public function reserve(Request $request): RedirectResponse
    {
        $application = $this->authorizeBooking($request);
        abort_unless(AppSetting::get('booking_mode') === 'slots', 404);
        $data = $request->validate(['slot_id' => ['required', 'integer', 'min:1']], ['slot_id.required' => '面談の日時を選んでください。']);

        try {
            $this->bookings->book($application, (int) $data['slot_id']);
        } catch (ApplicantFlowException $e) {
            if (in_array($e->kind, ['slot_taken', 'already_booked'], true)) {
                return redirect()->route('my.booking')->withErrors(['slot_id' => $e->info()['body']]);
            }
            throw $e;
        }

        return redirect()->route('my.booking')->with('status', '面談日程の予約を承りました。確認のメールをお送りします。');
    }

    public function go(Request $request): RedirectResponse
    {
        $application = $this->authorizeBooking($request);
        if (AppSetting::get('booking_mode') === 'slots') {
            return redirect()->route('my.booking');
        }
        $url = $this->bookingUrl($application);

        $interview = $application->interview;
        $interview->redirect_count++;
        $interview->first_redirected_at ??= now();
        if ($interview->booking_status !== 'confirmed') {
            $interview->booking_status = 'redirected';
        }
        $interview->save();

        return redirect()->away($url);
    }

    public function url(Request $request): JsonResponse
    {
        $application = $this->authorizeBooking($request);
        if (AppSetting::get('booking_mode') === 'slots') {
            return response()->json(['url' => route('my.booking'), 'number' => $application->number]);
        }

        return response()->json(['url' => $this->bookingUrl($application), 'number' => $application->number]);
    }

    private function authorizeBooking(Request $request): Application
    {
        /** @var Application $application */
        $application = $request->attributes->get('application');
        // access_granted は見積判定の通過、または管理者の手動許可でのみ true になる（取消で false）
        if (! $application->interview?->access_granted) {
            throw new ApplicantFlowException('forbidden');
        }

        return $application;
    }

    private function bookingUrl(Application $application): string
    {
        $url = AppSetting::get('booking_url');
        if (! $url) {
            app(AdminAlert::class)->raise('面談予約先が未設定です', $application, 'booking-unset:'.now()->format('Ymd'));
            throw new ApplicantFlowException('booking_unset');
        }

        return $url;
    }
}
