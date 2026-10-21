<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ConfigVersion;
use App\Models\Invitation;
use Illuminate\Support\Str;

class InvitationService
{
    /**
     * 招待を発行する。旧トークンはすべて失効させ、平文トークンは戻り値でのみ扱う（DB にはハッシュだけ）。
     * 応募にまだ募集設定が固定されていなければ、この時点の最新版を固定する。
     */
    public function issue(Application $application, ?int $adminId = null, bool $applyCurrentConfig = false): string
    {
        $this->revokeAll($application, '再発行');

        if ($application->config_version_id === null || $applyCurrentConfig) {
            $application->config_version_id = ConfigVersion::current()->id;
        }
        $config = $application->configVersion()->first() ?? ConfigVersion::current();

        $token = Str::random(48);
        $application->invitations()->create([
            'token_hash' => Invitation::hashToken($token),
            'expires_at' => now()->addDays($config->invite_ttl_days),
            'created_by' => $adminId,
        ]);

        if (in_array($application->status, ['received'], true)) {
            $application->status = 'invited';
        }
        $application->save();

        return $token;
    }

    public function revokeAll(Application $application, string $reason): int
    {
        return $application->invitations()->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoke_reason' => $reason]);
    }

    public function findByToken(string $token): ?Invitation
    {
        if (! preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
            return null;
        }

        return Invitation::query()->where('token_hash', Invitation::hashToken($token))->first();
    }

    public function activeInvitation(Application $application): ?Invitation
    {
        return $application->invitations()->whereNull('revoked_at')->latest('id')->first();
    }

    public function inviteUrl(string $token): string
    {
        return route('invite.show', ['token' => $token]);
    }
}
