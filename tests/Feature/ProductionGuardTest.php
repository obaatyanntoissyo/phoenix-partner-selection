<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ConfigVersion;
use App\Providers\AppServiceProvider;
use App\Support\ProductionGuard;
use Database\Seeders\DevSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Concerns\PartnerHelpers;
use Tests\TestCase;

/** T23 本番モードで開発用メール・テスト予約を指定 → 起動時または設定時に禁止 */
class ProductionGuardTest extends TestCase
{
    use PartnerHelpers, RefreshDatabase;

    private function productionConfig(array $overrides = []): void
    {
        config(array_merge([
            'app.debug' => false,
            'app.url' => 'https://partner.example.co.jp',
            'partner.dev_tools' => false,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.co.jp',
            'mail.mailers.smtp.port' => 587,
        ], $overrides));
    }

    public function test_t23_valid_production_config_has_no_violations(): void
    {
        $this->productionConfig();
        $this->assertSame([], ProductionGuard::violations());
    }

    public function test_t23_dev_mailbox_and_dev_tools_are_detected(): void
    {
        foreach ([
            ['mail.mailers.smtp.host' => 'mailpit'],
            ['mail.mailers.smtp.host' => '127.0.0.1'],
            ['mail.mailers.smtp.port' => 1025],
            ['mail.default' => 'log'],
            ['mail.default' => 'array'],
            ['partner.dev_tools' => true],
            ['app.debug' => true],
            ['app.url' => 'http://partner.example.co.jp'],
        ] as $bad) {
            $this->productionConfig($bad);
            $this->assertNotEmpty(ProductionGuard::violations(), json_encode($bad));
        }
    }

    public function test_t23_boot_fails_in_production_with_dev_settings(): void
    {
        $this->productionConfig(['mail.mailers.smtp.host' => 'mailpit']);
        $this->app['env'] = 'production';
        try {
            $this->expectException(RuntimeException::class);
            (new AppServiceProvider($this->app))->boot();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_t23_dev_booking_page_is_unavailable_in_production(): void
    {
        $this->get('/dev/booking')->assertOk();
        $this->app['env'] = 'production';
        $this->get('/dev/booking')->assertNotFound();
        $this->app['env'] = 'testing';
        config(['partner.dev_tools' => false]);
        $this->get('/dev/booking')->assertNotFound();
    }

    public function test_t23_dev_booking_url_and_test_criteria_rejected_in_production_settings(): void
    {
        $this->actingAs($this->admin());
        // 本番扱いでは CSRF 検証が有効になるため、この確認では外す（CSRF 自体は別テストで確認）
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app['env'] = 'production';
        try {
            foreach (['http://localhost:8000/dev/booking', 'https://partner.example.co.jp/dev/booking', 'http://127.0.0.1/x'] as $url) {
                $this->post('/admin/settings/app', ['booking_mode' => 'form', 'flow_mode' => 'direct', 'reapply_mode' => 'hold', 'booking_url' => $url])->assertSessionHasErrors('booking_url');
            }
            $current = ConfigVersion::current();
            $this->post('/admin/settings/config', [
                'mockup_links' => $current->mockup_links, 'scope_items' => $current->scope_items, 'estimate_items' => $current->estimate_items,
                'total_cap' => 100, 'invite_ttl_days' => 14, 'is_test_criteria' => '1', 'note' => 'x',
            ])->assertSessionHasErrors('is_test_criteria');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_csrf_token_is_required_outside_unit_tests(): void
    {
        $before = Application::query()->count();
        $this->app['env'] = 'production';
        try {
            $this->post('/apply/8341', $this->p2Data())->assertStatus(419);
            $this->post('/admin/login', ['email' => 'a@example.com', 'password' => 'x'])->assertStatus(419);
            $this->assertSame($before, Application::query()->count());
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_dev_seeder_refuses_production(): void
    {
        $this->app['env'] = 'production';
        try {
            $this->expectException(RuntimeException::class);
            (new DevSeeder)->run();
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
