<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\PartnerHelpers;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use PartnerHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useTestCriteria();
        $this->configureSettings();
    }

    /** T10 未認証・未提出・不通過者が予約URL取得APIを直接呼ぶ → 拒否、URLを返さない */
    public function test_t10_booking_url_api_is_denied_unless_passed(): void
    {
        // 未認証
        $this->getJson('/my/booking-url')->assertForbidden()->assertJsonMissing(['url' => 'https://booking.example.com/form']);
        $this->post('/my/booking/go')->assertForbidden();

        // 未提出
        $this->applyP1();
        $this->assertDenied($this->getJson('/my/booking-url'));
        $this->assertDenied($this->get('/my/booking'));
        $this->assertDenied($this->post('/my/booking/go'));

        // 不通過
        $this->submitEstimate(self::TEST_CAP + 1);
        $this->assertDenied($this->getJson('/my/booking-url'));
        $this->assertDenied($this->post('/my/booking/go'));

        // 通過者だけ取得できる
        $this->flushSession();
        $this->applyP1(['email' => 'ok@example.com']);
        $this->submitEstimate(self::TEST_CAP);
        $this->getJson('/my/booking-url')->assertOk()->assertJson(['url' => 'https://booking.example.com/form']);
    }

    /** T11 他人の応募番号・トークン、期限切れ・失効済みURL → 閲覧・予約不可 */
    public function test_t11_other_tokens_expired_and_revoked_links_are_rejected(): void
    {
        $this->applyP1();
        $victim = $this->latestApplication();
        $this->submitEstimate(self::TEST_CAP);
        $this->flushSession();

        // 応募番号やIDでは入れない
        $this->get('/invite/'.$victim->number)->assertNotFound();
        $this->get('/invite/'.$victim->id)->assertNotFound();
        $this->post('/invite', ['token' => $victim->number])->assertNotFound();
        $this->withSession(['applicant' => ['application_id' => $victim->id]])->get('/my/result')->assertForbidden();
        $this->flushSession();

        // 期限切れ
        $token = $this->inviteToken($victim);
        $this->invitationFor($token)->update(['expires_at' => now()->subMinute()]);
        $this->get('/invite/'.$token)->assertStatus(410)->assertSee('有効期限が切れています');
        $this->post('/invite', ['token' => $token])->assertStatus(410);

        // 再発行で旧URLは失効
        $old = $this->inviteToken($victim);
        $new = $this->inviteToken($victim);
        $this->get('/invite/'.$old)->assertNotFound();
        $this->post('/invite', ['token' => $new])->assertRedirect('/my');

        // 自分のセッションで他人の応募IDを指定しても、自分のデータしか扱わない
        $this->flushSession();
        $this->applyP1(['email' => 'attacker@example.com']);
        $this->get('/my/result?application_id='.$victim->id)->assertRedirect('/my/conditions');
        $this->getJson('/my/booking-url?application_id='.$victim->id)->assertForbidden();
    }

    public function test_revoked_invitation_ends_existing_session(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->get('/my/conditions')->assertOk();

        $this->actingAs($this->admin())->post("/admin/applications/{$application->id}/revoke-invites", ['reason' => 'テスト']);
        auth()->logout();
        $this->get('/my/conditions')->assertNotFound();
    }

    /** T18 招待リンクの事前GET → トークンが消費・失効しない */
    public function test_t18_prefetching_invite_link_does_not_consume_token(): void
    {
        AppSetting::put('flow_mode', 'email');
        $this->applyP1();
        $application = $this->latestApplication();
        $token = $this->inviteToken($application);

        for ($i = 0; $i < 3; $i++) {
            $this->get('/invite/'.$token)->assertOk()->assertSee('専用ページへ進む');
        }
        $invitation = $this->invitationFor($token);
        $this->assertNull($invitation->first_used_at);
        $this->assertNull($invitation->revoked_at);
        $this->get('/my/conditions')->assertForbidden();

        $this->post('/invite', ['token' => $token])->assertRedirect('/my');
        $this->get('/my')->assertRedirect('/my/conditions');
        $this->assertNotNull($invitation->fresh()->first_used_at);
        // 交換後もURLは有効（別の端末から再開できる）
        $this->get('/invite/'.$token)->assertOk();
    }

    /** T20 外部予約ページへ移動 → 移動履歴のみ。予約完了にしない */
    public function test_t20_redirect_to_booking_is_recorded_but_not_completed(): void
    {
        $this->applyP1();
        $this->submitEstimate(self::TEST_CAP);
        $this->get('/my/booking')->assertOk()->assertSee('応募番号')->assertSee('面談希望日時の登録')->assertDontSee('booking.example.com');

        $this->post('/my/booking/go')->assertRedirect('https://booking.example.com/form');
        $this->post('/my/booking/go');

        $interview = $this->latestApplication()->interview;
        $this->assertSame('redirected', $interview->booking_status);
        $this->assertSame(2, $interview->redirect_count);
        $this->assertNull($interview->interview_at);
        $this->assertSame('unbooked', $interview->stage);
    }

    public function test_booking_url_unset_is_a_configuration_error(): void
    {
        AppSetting::put('booking_url', null);
        $this->applyP1();
        $this->submitEstimate(self::TEST_CAP);
        $this->post('/my/booking/go')->assertStatus(503)->assertSee('現在手続きを進められません')->assertDontSee('合致しなかった');
        $this->assertDatabaseHas('email_jobs', ['type' => 'admin_alert']);
    }

    public function test_admin_revoke_removes_booking_access_and_manual_grant_restores_it(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->submitEstimate(self::TEST_CAP);
        $session = session('applicant');

        $this->actingAs($this->admin())->post("/admin/applications/{$application->id}/revoke-booking", ['reason' => '辞退の連絡'])->assertRedirect();
        auth()->logout();
        $this->withSession(['applicant' => $session])->getJson('/my/booking-url')->assertForbidden();

        $this->actingAs($this->admin())->post("/admin/applications/{$application->id}/grant-booking", ['reason' => '再開'])->assertRedirect();
        auth()->logout();
        $this->withSession(['applicant' => $session])->getJson('/my/booking-url')->assertOk();
        $this->assertSame(['auto_grant', 'revoke', 'manual_grant'], $application->permissionEvents()->pluck('action')->all());
    }

    /** T21 権限のない管理画面・管理APIアクセス → 拒否 */
    public function test_t21_admin_pages_require_login(): void
    {
        $this->applyP1();
        $application = $this->latestApplication();
        $this->flushSession();

        foreach (['/admin', '/admin/applications', "/admin/applications/{$application->id}", '/admin/applications/export', '/admin/settings', '/admin/templates'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
        foreach (["/admin/applications/{$application->id}/grant-booking", "/admin/applications/{$application->id}/allow-resubmission", '/admin/settings/config', '/admin/settings/app', '/admin/purge'] as $url) {
            $this->post($url, ['reason' => 'x'])->assertRedirect('/admin/login');
        }
        $this->getJson('/admin/applications')->assertUnauthorized();

        // 応募者のセッションでは管理画面に入れない
        $this->applyP1(['email' => 'x@example.com']);
        $this->get('/admin/applications')->assertRedirect('/admin/login');

        // 誤ったパスワード
        $this->admin();
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password-123456'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_security_headers_and_noindex(): void
    {
        $response = $this->get('/apply/partner');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
    }

    private function assertDenied(TestResponse $response): void
    {
        $response->assertForbidden();
        $this->assertStringNotContainsString('booking.example.com', $response->getContent());
    }
}
