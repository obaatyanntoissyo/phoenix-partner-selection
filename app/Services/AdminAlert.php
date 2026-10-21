<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AppSetting;
use App\Models\AuditLog;

/** 設定不備・送信障害などを社内へ通知する。同じ事象は dedupeKey で1回だけ。 */
class AdminAlert
{
    public function raise(string $alert, ?Application $application, string $dedupeKey): void
    {
        AuditLog::query()->firstOrCreate(
            ['action' => 'alert', 'reason' => mb_substr($alert.' / '.$dedupeKey, 0, 500)],
            ['target_type' => $application ? 'Application' : null, 'target_id' => $application?->id, 'admin_id' => null],
        );

        app(MailQueue::class)->enqueue('admin_alert', $application, AppSetting::notifyEmails(), [
            'alert' => $alert,
            'number' => $application?->number ?? '-',
            'admin_url' => $application ? route('admin.applications.show', $application) : route('admin.dashboard'),
        ], 'alert:'.$dedupeKey);
    }
}
