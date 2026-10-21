<?php

namespace App\Http\Controllers\Applicant;

use App\Exceptions\ApplicantFlowException;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 招待リンク。GET では何も消費・変更しない（メールのリンク検査対策）。
 * 確認ボタンの POST でセッションへ交換し、以後の URL からトークンを除く。
 */
class InviteController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function show(string $token): View
    {
        $invitation = $this->resolve($token);

        return view('invite.confirm', ['token' => $token, 'application' => $invitation->application]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $invitation = $this->resolve((string) $request->input('token'));

        $request->session()->regenerate();
        $request->session()->put('applicant', ['application_id' => $invitation->application_id, 'invitation_id' => $invitation->id]);
        if (! $invitation->first_used_at) {
            $invitation->update(['first_used_at' => now()]);
        }

        return redirect()->route('my.home');
    }

    private function resolve(string $token): Invitation
    {
        $invitation = $this->invitations->findByToken($token);
        if (! $invitation || $invitation->isRevoked() || ! $invitation->application || $invitation->application->anonymized_at || ! $invitation->application->isP1()) {
            throw new ApplicantFlowException('invalid');
        }
        if ($invitation->isExpired()) {
            throw new ApplicantFlowException('expired');
        }

        return $invitation;
    }
}
