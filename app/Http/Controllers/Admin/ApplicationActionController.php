<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\EmailJob;
use App\Models\Interview;
use App\Models\PermissionEvent;
use App\Services\Anonymizer;
use App\Services\ApplicationService;
use App\Services\InvitationService;
use App\Services\MailQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** 管理者操作。すべて理由と操作者を記録する。 */
class ApplicationActionController extends Controller
{
    public function __construct(private InvitationService $invitations, private MailQueue $mail) {}

    /** 招待の再発行（旧URLは失効）。apply_current_config で最新の募集条件を適用し直す。 */
    public function reinvite(Request $request, Application $application, ApplicationService $service): RedirectResponse
    {
        abort_unless($application->isP1() && ! $application->anonymized_at, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'apply_current_config' => ['nullable', 'boolean']]);
        $applyCurrent = (bool) ($data['apply_current_config'] ?? false);
        $before = $application->config_version_id;

        $token = DB::transaction(function () use ($application, $applyCurrent, $data, $service, $before) {
            $token = $this->invitations->issue($application, $this->adminId(), $applyCurrent);
            $invitation = $this->invitations->activeInvitation($application);
            $this->mail->enqueue('p1_invitation', $application, [$application->email], $service->inviteVars($application->refresh(), $token), 'invite:'.$invitation->id, $this->adminId());
            AuditLog::record('reinvite', $application, $data['reason'], ['config_version_before' => $before, 'config_version_after' => $application->config_version_id]);

            return $token;
        });
        unset($token);

        return back()->with('status', '招待を再発行し、メールを送信待ちにしました。以前のURLは使えなくなりました。');
    }

    public function revokeInvites(Request $request, Application $application): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $count = $this->invitations->revokeAll($application, $data['reason']);
        AuditLog::record('revoke_invites', $application, $data['reason'], ['count' => $count]);

        return back()->with('status', '招待URLを失効させました。');
    }

    /** 再提出の許可。過去の提出と判定は残したまま、提出可能回数を1回増やす。 */
    public function allowResubmission(Request $request, Application $application): RedirectResponse
    {
        abort_unless($application->isP1() && $application->submissions()->exists(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($application, $data) {
            $application->update(['allowed_submissions' => $application->submissions()->count() + 1, 'status' => 'drafting']);
            AuditLog::record('allow_resubmission', $application, $data['reason']);
        });

        return back()->with('status', '再提出を許可しました。招待URLの期限が切れている場合は再招待してください。');
    }

    public function grantBooking(Request $request, Application $application): RedirectResponse
    {
        abort_unless($application->isP1(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($application, $data) {
            $interview = Interview::query()->firstOrCreate(['application_id' => $application->id]);
            $interview->update(['access_granted' => true, 'booking_status' => $interview->booking_status === 'not_guided' ? 'available' : $interview->booking_status]);
            PermissionEvent::create(['application_id' => $application->id, 'action' => 'manual_grant', 'reason' => $data['reason'], 'admin_id' => $this->adminId()]);
            AuditLog::record('manual_grant', $application, $data['reason']);
        });

        return back()->with('status', '面談予約への案内を手動で許可しました。');
    }

    /** 予約権限の取消。本アプリ内の権限だけを失効し、外部の既存予約は取り消さない。 */
    public function revokeBooking(Request $request, Application $application): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        DB::transaction(function () use ($application, $data) {
            Interview::query()->where('application_id', $application->id)->update(['access_granted' => false]);
            PermissionEvent::create(['application_id' => $application->id, 'action' => 'revoke', 'reason' => $data['reason'], 'admin_id' => $this->adminId()]);
            AuditLog::record('revoke_booking', $application, $data['reason']);
        });

        return back()->with('status', '予約案内の権限を取り消しました。外部予約サービス側の既存予約は自動で取り消されません。');
    }

    public function updateInterview(Request $request, Application $application): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(Interview::STAGE_LABELS))],
            'interview_at' => ['nullable', 'date'],
            'booking_confirmed' => ['nullable', 'boolean'],
        ]);
        $interview = Interview::query()->firstOrCreate(['application_id' => $application->id]);
        $before = $interview->only(['stage', 'interview_at', 'booking_status']);
        $confirmed = (bool) ($data['booking_confirmed'] ?? false);
        if ($confirmed && empty($data['interview_at'])) {
            return back()->withErrors(['interview_at' => '予約確認済みにする場合は面談日時を入力してください。']);
        }
        $interview->update([
            'stage' => $data['stage'],
            'interview_at' => $data['interview_at'] ?? null,
            'booking_status' => $confirmed ? 'confirmed' : ($interview->booking_status === 'confirmed' ? ($interview->first_redirected_at ? 'redirected' : 'available') : $interview->booking_status),
            'confirmed_by' => $confirmed ? $this->adminId() : $interview->confirmed_by,
        ]);
        AuditLog::record('update_interview', $application, null, ['before' => $before, 'after' => $interview->only(['stage', 'interview_at', 'booking_status'])]);

        return back()->with('status', '面談状況を更新しました。');
    }

    public function resendMail(Request $request, Application $application, EmailJob $job): RedirectResponse
    {
        abort_unless($job->application_id === $application->id, 404);
        if (in_array($job->type, ['p1_receipt', 'p1_invitation', 'p1_result_passed'], true)) {
            // 招待URLを含むメールは、失効済みのURLを再送しないよう再招待で送り直す
            return back()->withErrors(['resend' => '本人用URLを含むメールは「再招待」で新しいURLを発行して送ってください。']);
        }
        $new = $this->mail->resend($job, $this->adminId());
        AuditLog::record('resend_mail', $application, null, ['email_job_id' => $job->id, 'new_email_job_id' => $new->id]);

        return back()->with('status', 'メールを再送の送信待ちにしました。');
    }

    public function anonymize(Request $request, Application $application, Anonymizer $anonymizer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'confirm' => ['accepted']]);
        $anonymizer->anonymize($application, $this->adminId(), $data['reason']);

        return back()->with('status', '個人情報を削除し、招待URLを失効させました。');
    }

    private function adminId(): int
    {
        return (int) auth()->id();
    }
}
