<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AppSetting;
use App\Models\EmailJob;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PartnerHelpers;
use Tests\TestCase;

class ApplicationIntakeTest extends TestCase
{
    use PartnerHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useTestCriteria();
        $this->configureSettings();
    }

    /** T01 ①から応募 → ①として保存、①用の受付メール（招待URL付き）のみ */
    public function test_t01_p1_application_is_saved_and_only_p1_mail_is_queued(): void
    {
        $this->applyP1()->assertRedirect('/my/conditions');

        $application = $this->latestApplication();
        $this->assertSame('p1', $application->category);
        $this->assertMatchesRegularExpression('/^P1-\d{4}-00001$/', $application->number);
        $this->assertSame(1, $application->invitations()->count());

        $types = EmailJob::query()->pluck('type')->all();
        $this->assertEqualsCanonicalizing(['p1_receipt', 'admin_new_application'], $types);
        $receipt = EmailJob::query()->where('type', 'p1_receipt')->first();
        $this->assertStringContainsString('/invite/', $receipt->body);
        $this->assertStringContainsString($application->number, $receipt->body);
        $this->assertStringNotContainsString((string) self::TEST_CAP, $receipt->body);
        $this->assertStringNotContainsString(number_format(self::TEST_CAP), $receipt->body);

        // 社内通知には応募内容を転載しない
        $admin = EmailJob::query()->where('type', 'admin_new_application')->first();
        $this->assertStringNotContainsString('taro@example.com', $admin->body);
        $this->assertStringNotContainsString('090-1234-5678', $admin->body);
    }

    /** T02 ②から応募 → ②として保存、②用返信のみ。見積招待なし */
    public function test_t02_p2_application_gets_no_estimate_invitation(): void
    {
        $this->post('/apply/8341', $this->p2Data())->assertRedirect('/apply/done');

        $application = $this->latestApplication();
        $this->assertSame('p2', $application->category);
        $this->assertSame(0, Invitation::query()->count());
        $this->assertNull($application->interview);

        $job = EmailJob::query()->where('type', 'p2_receipt')->firstOrFail();
        // ②のテンプレートは社内確認前のため送信無効（skipped）
        $this->assertSame('skipped', $job->status);
        $this->assertStringNotContainsString('/invite/', $job->body);
        $this->assertStringNotContainsString('netlify', $job->body);
        $this->assertFalse(EmailJob::query()->whereIn('type', ['p1_receipt', 'p1_invitation'])->exists());

        // ②のセッションでは①の見積画面へ入れない
        $this->get('/my/estimate')->assertForbidden();
    }

    public function test_p2_cannot_be_turned_into_p1_by_form_field(): void
    {
        $this->post('/apply/8341', $this->p2Data(['category' => 'p1']))->assertRedirect('/apply/done');
        $this->assertSame('p2', $this->latestApplication()->category);
    }

    public function test_p1_conditions_not_met_are_still_saved(): void
    {
        $this->applyP1(['experience_years' => '1', 'can_full_scope' => '0'])->assertRedirect('/my/conditions');
        $application = $this->latestApplication();
        $this->assertFalse($application->can_full_scope);
        $this->assertSame(1, $application->experience_years);
    }

    public function test_required_fields_keep_values_and_show_japanese_errors(): void
    {
        $this->from('/apply/partner')->applyP1(['email' => 'not-an-email', 'name' => '', 'privacy' => null])
            ->assertRedirect('/apply/partner');
        $this->assertSame(0, Application::query()->count());

        $this->get('/apply/partner')
            ->assertSee('氏名を入力してください。')
            ->assertSee('メールアドレスの形式が正しくありません。')
            ->assertSee('プライバシーポリシーへの同意が必要です。')
            ->assertSee('ヤマダ タロウ');
    }

    public function test_email_mode_sends_user_to_done_page_instead_of_estimate(): void
    {
        AppSetting::put('flow_mode', 'email');
        $this->applyP1()->assertRedirect('/apply/done');
        $this->get('/my/conditions')->assertForbidden();
    }

    /** T13 同じメールで再応募 → 記録は保存し既存へ紐付け、招待は自動発行しない */
    public function test_t13_reapplication_with_same_email_is_linked_without_new_invitation(): void
    {
        $this->applyP1();
        $first = $this->latestApplication();
        $this->flushSession();

        $this->applyP1(['email' => 'TARO@example.com '])->assertRedirect('/apply/done');
        $second = $this->latestApplication();

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->id, $second->linked_application_id);
        $this->assertSame(0, $second->invitations()->count());
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_receipt')->count());
        $this->get('/my/conditions')->assertForbidden();
    }

    public function test_per_email_rate_limit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->flushSession();
            $this->post('/apply/8341', $this->p2Data());
        }
        $this->flushSession();
        $this->post('/apply/8341', $this->p2Data())->assertSessionHasErrors('email');
        $this->assertSame(3, Application::query()->count());
    }

    public function test_intake_api_requires_key_and_is_idempotent(): void
    {
        $payload = $this->p1Data() + ['event_id' => 'form-123', 'category' => 'p1'];

        $this->postJson('/api/intake/applications', $payload)->assertUnauthorized();
        $this->postJson('/api/intake/applications', $payload, ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        $headers = ['Authorization' => 'Bearer test-intake-key-0123456789abcdef0123456789'];
        $first = $this->postJson('/api/intake/applications', $payload, $headers)->assertCreated();
        $this->assertStringContainsString('/invite/', $first->json('next_url'));

        $again = $this->postJson('/api/intake/applications', $payload, $headers)->assertOk();
        $this->assertTrue($again->json('replayed'));
        $this->assertSame($first->json('number'), $again->json('number'));
        $this->assertSame(1, Application::query()->count());
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_receipt')->count());
    }
}
