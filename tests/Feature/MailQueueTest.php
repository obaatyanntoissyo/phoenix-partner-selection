<?php

namespace Tests\Feature;

use App\Models\EmailJob;
use App\Services\MailQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Feature\Concerns\PartnerHelpers;
use Tests\TestCase;

class MailQueueTest extends TestCase
{
    use PartnerHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useTestCriteria();
        $this->configureSettings();
    }

    public function test_worker_sends_pending_mail_once(): void
    {
        $this->applyP1();
        $this->artisan('partner:send-mail')->assertSuccessful();

        $this->assertSame(2, EmailJob::query()->where('status', 'accepted')->count());
        $this->artisan('partner:send-mail')->assertSuccessful();
        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(2, $sent);
        $subjects = collect($sent)->map(fn ($m) => $m->getOriginalMessage()->getSubject())->all();
        $this->assertContains('【フェニックス】一括制作パートナー応募受付とお見積もりのお願い', $subjects);
    }

    /** T17 メール送信失敗と再試行 → DB記録は残る。再試行し、重複送信しない */
    public function test_t17_failed_mail_is_retried_without_losing_the_application(): void
    {
        $this->applyP1()->assertRedirect('/my/conditions');
        $application = $this->latestApplication();

        Mail::shouldReceive('raw')->twice()->andThrow(new RuntimeException('SMTP down for taro@example.com'));
        app(MailQueue::class)->processDue();

        $this->assertDatabaseHas('applications', ['id' => $application->id]);
        $jobs = EmailJob::query()->get();
        $this->assertTrue($jobs->every(fn ($j) => $j->status === 'failed' && $j->attempts === 1));
        $this->assertStringNotContainsString('taro@example.com', $jobs->first()->last_error);

        // 次回時刻前は再送しない
        app(MailQueue::class)->processDue();
        $this->assertSame(1, EmailJob::query()->max('attempts'));

        // 次回時刻になったら再試行して成功
        Mail::swap(new MailManager($this->app));
        EmailJob::query()->update(['next_attempt_at' => now()->subSecond()]);
        app(MailQueue::class)->processDue();
        $this->assertSame(2, EmailJob::query()->where('status', 'accepted')->count());
        app(MailQueue::class)->processDue();
        $this->assertSame(2, EmailJob::query()->where('status', 'accepted')->count());
    }

    public function test_mail_becomes_dead_after_max_attempts_and_alerts_admin(): void
    {
        $this->post('/apply/8341', $this->p2Data());
        $job = EmailJob::query()->where('type', 'admin_new_application')->firstOrFail();
        Mail::shouldReceive('raw')->andThrow(new RuntimeException('down'));

        for ($i = 0; $i < MailQueue::MAX_ATTEMPTS; $i++) {
            EmailJob::query()->whereKey($job->id)->update(['next_attempt_at' => now()->subSecond()]);
            app(MailQueue::class)->send($job->fresh());
        }
        $this->assertSame('dead', $job->fresh()->status);
        $this->assertSame(1, EmailJob::query()->where('type', 'admin_alert')->count());
    }

    public function test_passed_result_survives_mail_failure(): void
    {
        $this->applyP1();
        $this->submitEstimate(self::TEST_CAP);
        Mail::shouldReceive('raw')->andThrow(new RuntimeException('down'));
        app(MailQueue::class)->processDue();

        $this->assertTrue($this->latestApplication()->interview->access_granted);
        $this->getJson('/my/booking-url')->assertOk();
    }

    public function test_body_is_encrypted_at_rest(): void
    {
        $this->applyP1();
        $raw = \DB::table('email_jobs')->where('type', 'p1_receipt')->value('body');
        $this->assertStringNotContainsString('/invite/', $raw);
        $this->assertStringNotContainsString('山田', $raw);
    }

    public function test_enqueue_is_idempotent(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $queue = app(MailQueue::class);
        $a = $queue->enqueue('admin_alert', $application, ['team@example.com'], ['alert' => 'x'], 'same-key');
        $b = $queue->enqueue('admin_alert', $application, ['team@example.com'], ['alert' => 'x'], 'same-key');
        $this->assertSame($a->id, $b->id);
    }

    public function test_admin_resend_is_recorded(): void
    {
        $this->post('/apply/8341', $this->p2Data());
        $application = $this->latestApplication();
        $job = EmailJob::query()->where('type', 'admin_new_application')->firstOrFail();
        $job->update(['status' => 'dead']);

        $this->actingAs($this->admin())->post("/admin/applications/{$application->id}/mail/{$job->id}/resend")->assertRedirect();
        $this->assertSame(2, EmailJob::query()->where('type', 'admin_new_application')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'resend_mail', 'target_id' => $application->id]);
    }
}
