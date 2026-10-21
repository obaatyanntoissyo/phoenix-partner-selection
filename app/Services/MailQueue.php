<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AppSetting;
use App\Models\EmailJob;
use App\Models\EmailTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * 送信待ちを DB に積み、別プロセス（partner:send-mail）で送る。
 * 保存処理とメール送信を分離し、送信失敗で応募・提出を失敗にしない。
 */
class MailQueue
{
    public const MAX_ATTEMPTS = 5;

    /**
     * 冪等キーが同じジョブは二度作らない。既存があればそれを返す。
     */
    public function enqueue(string $type, ?Application $application, array $to, array $vars, string $idempotencyKey, ?int $adminId = null): ?EmailJob
    {
        $existing = EmailJob::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        $template = EmailTemplate::currentFor($type);
        $to = array_values(array_filter($to));
        $vars += ['contact' => (string) AppSetting::get('contact_text', '')];
        $rendered = $template ? $template->render($vars) : ['subject' => '', 'body' => ''];
        $status = ($template && $template->enabled && $to) ? 'pending' : 'skipped';

        try {
            return EmailJob::create([
                'idempotency_key' => $idempotencyKey,
                'application_id' => $application?->id,
                'type' => $type,
                'template_id' => $template?->id,
                'to_address' => implode(',', $to),
                'subject' => $rendered['subject'],
                'body' => $rendered['body'],
                'status' => $status,
                'next_attempt_at' => $status === 'pending' ? now() : null,
                'last_error' => $status === 'skipped' ? ($to ? 'テンプレートが送信無効' : '宛先未設定') : null,
                'created_by' => $adminId,
            ]);
        } catch (QueryException $e) {
            // 同時実行で一意制約に当たった場合は既存のジョブを使う
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                return EmailJob::query()->where('idempotency_key', $idempotencyKey)->first();
            }
            throw $e;
        }
    }

    /** 送信待ち・再試行待ちを処理し、処理件数を返す。 */
    public function processDue(int $limit = 20): int
    {
        $processed = 0;
        for ($i = 0; $i < $limit; $i++) {
            $job = DB::transaction(function () {
                $job = EmailJob::query()
                    ->whereIn('status', ['pending', 'failed'])
                    ->where('next_attempt_at', '<=', now())
                    ->orderBy('id')
                    ->lock('for update skip locked')
                    ->first();
                if (! $job) {
                    return null;
                }
                // 処理中に他のワーカーが拾わないよう、次回時刻を先に進める
                $job->update(['next_attempt_at' => now()->addMinutes(10)]);

                return $job;
            });
            if (! $job) {
                break;
            }
            $this->send($job);
            $processed++;
        }

        return $processed;
    }

    public function send(EmailJob $job): void
    {
        $job->attempts++;
        try {
            $to = explode(',', $job->to_address);
            Mail::raw($job->body, function ($message) use ($job, $to) {
                $message->to($to)->subject($job->subject);
            });
            $job->status = 'accepted';
            $job->accepted_at = now();
            $job->next_attempt_at = null;
            $job->last_error = null;
            $job->save();
        } catch (Throwable $e) {
            // 例外メッセージに宛先や本文が含まれる可能性があるため、種類だけを残す
            $job->last_error = mb_substr(class_basename($e).': '.preg_replace('/\S+@\S+/', '[email]', $e->getMessage()), 0, 480);
            if ($job->attempts >= self::MAX_ATTEMPTS) {
                $job->status = 'dead';
                $job->next_attempt_at = null;
            } else {
                $job->status = 'failed';
                $job->next_attempt_at = now()->addMinutes(2 ** $job->attempts);
            }
            $job->save();
            Log::warning('mail send failed', ['email_job_id' => $job->id, 'attempts' => $job->attempts]);

            if ($job->status === 'dead' && $job->type !== 'admin_alert') {
                app(AdminAlert::class)->raise('メール送信失敗（再試行上限）', $job->application_id ? Application::find($job->application_id) : null, 'mail-dead:'.$job->id);
            }
        }
    }

    /** 管理者による手動再送。操作として記録し、新しいジョブとして送る。 */
    public function resend(EmailJob $job, int $adminId): EmailJob
    {
        return EmailJob::create([
            'idempotency_key' => $job->idempotency_key.':resend:'.now()->format('YmdHisv'),
            'application_id' => $job->application_id,
            'type' => $job->type,
            'template_id' => $job->template_id,
            'to_address' => $job->to_address,
            'subject' => $job->subject,
            'body' => $job->body,
            'status' => 'pending',
            'next_attempt_at' => now(),
            'created_by' => $adminId,
        ]);
    }
}
