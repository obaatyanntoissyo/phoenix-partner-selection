<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/** 保存期間に沿った個人情報の削除。見積の金額・判定の記録は個人を特定しない形で残す。 */
class Anonymizer
{
    public function __construct(private InvitationService $invitations) {}

    public function anonymize(Application $application, ?int $adminId, string $reason): void
    {
        DB::transaction(function () use ($application, $adminId, $reason) {
            $this->invitations->revokeAll($application, '個人情報の削除');
            $application->draft()?->delete();
            $application->emailJobs()->each(fn ($job) => $job->update(['to_address' => '', 'subject' => '', 'body' => '']));
            $application->update([
                'name' => '（削除済み）',
                'kana' => '（削除済み）',
                'company' => null,
                'email' => 'deleted-'.$application->id.'@invalid.invalid',
                'email_normalized' => 'deleted:'.$application->id,
                'phone' => '-',
                'portfolio_url' => null,
                'role_scope' => null,
                'self_intro' => null,
                'status' => 'anonymized',
                'anonymized_at' => now(),
            ]);
            AuditLog::record('anonymize', $application, $reason, [], $adminId);
        });
    }

    public function purgeOlderThan(int $days, ?int $adminId): int
    {
        $count = 0;
        Application::query()
            ->whereNull('anonymized_at')
            ->where('created_at', '<', now()->subDays($days))
            ->each(function (Application $application) use (&$count, $adminId, $days) {
                $this->anonymize($application, $adminId, "保存期間（{$days}日）経過による削除");
                $count++;
            });
        AuditLog::record('purge', null, "保存期間 {$days} 日", ['count' => $count], $adminId);

        return $count;
    }
}
