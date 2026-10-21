<?php

namespace App\Http\Middleware;

use App\Exceptions\ApplicantFlowException;
use App\Models\Application;
use App\Models\Invitation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 応募者本人のセッションを確認する。応募IDはセッションからのみ取り、URLやフォームの値は信用しない。
 * 招待が失効していればアクセスを拒否する（再発行・管理者による取消を即時に反映）。
 */
class ApplicantSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $applicationId = $request->session()->get('applicant.application_id');
        $invitationId = $request->session()->get('applicant.invitation_id');
        if (! $applicationId || ! $invitationId) {
            throw new ApplicantFlowException('session');
        }

        $invitation = Invitation::query()->find($invitationId);
        $application = Application::query()->find($applicationId);
        if (! $invitation || ! $application || $invitation->application_id !== $application->id) {
            $request->session()->forget('applicant');
            throw new ApplicantFlowException('session');
        }
        if ($invitation->isRevoked() || $application->anonymized_at !== null || ! $application->isP1()) {
            $request->session()->forget('applicant');
            throw new ApplicantFlowException('invalid');
        }

        $request->attributes->set('application', $application);
        $request->attributes->set('invitation', $invitation);

        return $next($request);
    }
}
