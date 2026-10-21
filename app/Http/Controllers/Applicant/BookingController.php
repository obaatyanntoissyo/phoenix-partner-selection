<?php

namespace App\Http\Controllers\Applicant;

use App\Exceptions\ApplicantFlowException;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AppSetting;
use App\Services\AdminAlert;
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
    public function show(Request $request): View
    {
        $application = $this->authorizeBooking($request);

        return view('my.booking', [
            'application' => $application,
            'mode' => AppSetting::get('booking_mode', 'form'),
            'interview' => $application->interview,
        ]);
    }

    public function go(Request $request): RedirectResponse
    {
        $application = $this->authorizeBooking($request);
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
