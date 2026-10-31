<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AppSetting;
use App\Models\ConfigVersion;
use App\Models\EmailJob;
use App\Models\EmailTemplate;
use App\Models\ScreeningResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\PartnerHelpers;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use PartnerHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useTestCriteria();
        $this->configureSettings();
    }

    /** T15 管理者が基準変更 → 旧応募の条件・結果は変化しない。新招待に新基準を適用 */
    public function test_t15_config_change_does_not_affect_existing_applications(): void
    {
        $this->applyP1();
        $old = $this->latestApplication();
        $oldSession = session('applicant');
        $oldVersion = $old->config_version_id;
        $this->submitEstimate(800_000);
        $this->assertSame('passed', ScreeningResult::query()->sole()->outcome);

        // 未提出の別応募も、招待時の版で判定される
        $this->flushSession();
        $this->applyP1(['email' => 'pending@example.com']);
        $pending = $this->latestApplication();
        $pendingSession = session('applicant');

        $current = ConfigVersion::current();
        $items = $current->estimate_items;
        $this->actingAs($this->admin())->post('/admin/settings/config', [
            'mockup_links' => $current->mockup_links,
            'scope_items' => $current->scope_items,
            'estimate_items' => $items,
            'total_cap' => 500_000,
            'invite_ttl_days' => 7,
            'is_test_criteria' => '1',
            'note' => '上限を引き下げ（テスト）',
        ])->assertRedirect();
        auth()->logout();
        $newVersion = ConfigVersion::current();
        $this->assertSame(500_000, $newVersion->total_cap);

        // 既存の結果は変わらない
        $this->assertSame('passed', ScreeningResult::query()->sole()->outcome);
        $this->assertSame($oldVersion, $old->fresh()->config_version_id);

        // 未提出の既存応募も旧基準（招待時に固定）で判定
        $this->withSession(['applicant' => $pendingSession]);
        $this->submitEstimate(800_000);
        $this->assertSame('passed', ScreeningResult::query()->where('application_id', $pending->id)->sole()->outcome);

        // 新しい応募には新基準
        $this->flushSession();
        $this->applyP1(['email' => 'new@example.com']);
        $this->assertSame($newVersion->id, $this->latestApplication()->config_version_id);
        $this->submitEstimate(800_000);
        $this->assertSame('held', ScreeningResult::query()->latest('id')->first()->outcome);
        $this->assertSame(500_000, ScreeningResult::query()->latest('id')->first()->criteria['total_cap']);
    }

    public function test_reinvite_with_current_config_revokes_old_link_and_applies_new_version(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $session = session('applicant');
        $newVersion = $this->useTestCriteria(2_000_000);

        $this->actingAs($this->admin())->post("/admin/applications/{$application->id}/reinvite", ['reason' => '条件改定', 'apply_current_config' => '1'])->assertRedirect();
        auth()->logout();

        $this->assertSame($newVersion->id, $application->fresh()->config_version_id);
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_invitation')->count());
        $this->withSession(['applicant' => $session])->get('/my/conditions')->assertNotFound();
    }

    public function test_manual_registration_with_preview_and_duplicate_protection(): void
    {
        $this->actingAs($this->admin());
        $data = ['name' => '過去 問合せ', 'kana' => 'カコ トイアワセ', 'email' => 'past@example.com', 'experience_years' => '4', 'experience_months' => '0', 'monthly_hours' => '60', 'available_from' => '2026-12-01'];

        $this->post('/admin/applications/preview', $data)->assertOk()->assertSee('お見積もり入力のご案内')->assertSee('past@example.com');
        $this->post('/admin/applications', $data + ['send_invite' => '1'])->assertRedirect();
        $application = Application::query()->where('email', 'past@example.com')->sole();
        $this->assertSame('admin', $application->source);
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_invitation')->count());
        $this->assertFalse(EmailJob::query()->where('type', 'admin_new_application')->exists());

        // 同じメールで再登録しても招待は自動で送らない
        $this->post('/admin/applications', $data + ['send_invite' => '1'])->assertRedirect();
        $this->assertSame(1, EmailJob::query()->where('type', 'p1_invitation')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'manual_register']);
    }

    public function test_csv_export_escapes_formulas_and_labels_tax(): void
    {
        $this->applyP1(['name' => '=HYPERLINK("http://evil")', 'company' => '+cmd', 'self_intro' => '@x']);
        $this->submitEstimate(300_000);
        $this->flushSession();

        $csv = $this->actingAs($this->admin())->get('/admin/applications/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('見積合計（税別・円）', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+cmd", $csv);
        $this->assertStringContainsString('300000', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'csv_export']);
    }

    public function test_admin_pages_render(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->submitEstimate(300_000);
        $this->flushSession();
        $this->actingAs($this->admin());

        $this->get('/admin')->assertOk()->assertSee('公開前に設定が必要な項目')->assertSee('テスト用の仮基準');
        $this->get('/admin/applications?category=p1&q=山田')->assertOk()->assertSee($application->number);
        $this->get('/admin/applications?outcome=failed')->assertOk()->assertDontSee($application->number);
        $this->get("/admin/applications/{$application->id}")->assertOk()->assertSee('通過')->assertSee('300,000');
        $this->get('/admin/settings')->assertOk();
        $this->get('/admin/templates')->assertOk();
        $this->get('/admin/templates/p2_receipt')->assertOk()->assertSee('1,500円');
    }

    public function test_interview_status_is_managed_separately(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->submitEstimate(300_000);
        $this->actingAs($this->admin());

        $this->post("/admin/applications/{$application->id}/interview", ['stage' => 'booked', 'booking_confirmed' => '1'])->assertSessionHasErrors('interview_at');
        $this->post("/admin/applications/{$application->id}/interview", ['stage' => 'booked', 'booking_confirmed' => '1', 'interview_at' => '2026-11-10 10:00'])->assertRedirect();
        $interview = $application->interview()->first();
        $this->assertSame('confirmed', $interview->booking_status);
        $this->assertSame('passed', ScreeningResult::query()->sole()->outcome);
    }

    public function test_template_edit_creates_new_version_and_blocks_mockup_url_in_p2(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/templates/p2_receipt', ['subject' => 's', 'body' => '見積 {invite_url}', 'enabled' => '1'])->assertSessionHasErrors('body');
        $this->post('/admin/templates/p2_receipt', ['subject' => '件名', 'body' => '{name} 様 ありがとうございます', 'enabled' => '1'])->assertRedirect();
        $this->assertSame(2, EmailTemplate::currentFor('p2_receipt')->version_no);
        $this->assertTrue(EmailTemplate::currentFor('p2_receipt')->enabled);
        $this->assertSame(2, EmailTemplate::query()->where('type', 'p2_receipt')->count());
    }

    public function test_anonymize_and_purge(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $session = session('applicant');
        $this->submitEstimate(300_000);

        $this->actingAs($this->admin());
        $this->post('/admin/purge', ['confirm' => '1'])->assertSessionHasErrors('confirm');

        AppSetting::put('retention_days', '30');
        $this->travel(31)->days();
        $this->post('/admin/purge', ['confirm' => '1'])->assertRedirect();
        $fresh = $application->fresh();
        $this->assertNotNull($fresh->anonymized_at);
        $this->assertSame('（削除済み）', $fresh->name);
        $this->assertSame(1, $fresh->submissions()->count());
        $this->assertSame('', EmailJob::query()->where('application_id', $application->id)->first()->body);
        auth()->logout();
        $this->withSession(['applicant' => $session])->get('/my/result')->assertNotFound();
    }

    public function test_settings_validation(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/settings/app', ['booking_mode' => 'form', 'flow_mode' => 'direct', 'reapply_mode' => 'hold', 'notify_emails' => 'ok@example.com, bad'])->assertSessionHasErrors('notify_emails');
        $this->post('/admin/settings/app', ['booking_mode' => 'form', 'flow_mode' => 'direct', 'reapply_mode' => 'hold', 'booking_url' => 'https://forms.example.com/x', 'retention_days' => '365'])->assertSessionHasNoErrors();
        $this->assertSame('https://forms.example.com/x', AppSetting::get('booking_url'));
    }
}
