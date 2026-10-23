<?php

namespace Tests\Feature\Concerns;

use App\Models\Application;
use App\Models\AppSetting;
use App\Models\ConfigVersion;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Testing\TestResponse;

/**
 * テスト共通の手順。金額の判定はテスト専用の仮基準（TEST_CAP）を使い、本番基準として流用しない。
 */
trait PartnerHelpers
{
    protected const TEST_CAP = 1_000_000;

    protected function useTestCriteria(?int $cap = self::TEST_CAP, bool $itemCaps = false, array $capsByKey = []): ConfigVersion
    {
        $current = ConfigVersion::current();
        $items = array_map(fn ($i) => ['cap' => $capsByKey[$i['key']] ?? null] + $i, $current->estimate_items);

        return ConfigVersion::create([
            'version_no' => $current->version_no + 1,
            'mockup_links' => $current->mockup_links,
            'scope_items' => $current->scope_items,
            'estimate_items' => $items,
            'total_cap' => $cap,
            'item_caps_enabled' => $itemCaps,
            'invite_ttl_days' => 14,
            'is_test_criteria' => true,
            'note' => 'テスト用の仮基準',
        ]);
    }

    protected function configureSettings(): void
    {
        AppSetting::put('booking_url', 'https://booking.example.com/form');
        AppSetting::put('notify_emails', 'team@example.com');
        AppSetting::put('flow_mode', 'direct');
    }

    protected function p1Data(array $overrides = []): array
    {
        return array_merge([
            'name' => '山田 太郎',
            'kana' => 'ヤマダ タロウ',
            'company' => '山田デザイン',
            'email' => 'taro@example.com',
            'phone' => '090-1234-5678',
            'prefecture' => '静岡県',
            'experience_years' => '5',
            'experience_months' => '0',
            'portfolio_url' => 'https://portfolio.example.com',
            'role_scope' => 'ディレクションから公開まで担当',
            'monthly_hours' => '80',
            'available_from' => now()->addWeek()->format('Y-m-d'),
            'can_full_scope' => '1',
            'can_online_meeting' => '1',
            'self_intro' => 'よろしくお願いします。',
            'privacy' => '1',
        ], $overrides);
    }

    protected function p2Data(array $overrides = []): array
    {
        $data = $this->p1Data($overrides + ['email' => 'cms@example.com']);
        unset($data['can_full_scope'], $data['can_online_meeting']);

        return $data;
    }

    protected function applyP1(array $overrides = []): TestResponse
    {
        return $this->post('/apply/partner', $this->p1Data($overrides));
    }

    protected function latestApplication(): Application
    {
        return Application::query()->latest('id')->firstOrFail();
    }

    /** 金額の入力値（キー => 値）。合計 = 各値の和 */
    protected function amounts(int $total): array
    {
        $keys = array_column(ConfigVersion::current()->estimate_items, 'key');
        $each = intdiv($total, count($keys));
        $amounts = array_fill_keys($keys, (string) $each);
        $amounts[$keys[0]] = (string) ($total - $each * (count($keys) - 1));

        return $amounts;
    }

    /** 想定工数の入力値（すべて10時間） */
    protected function hours(int $each = 10): array
    {
        return array_fill_keys(array_column(ConfigVersion::current()->estimate_items, 'key'), (string) $each);
    }

    protected function estimateInput(int $total, array $overrides = []): array
    {
        return array_replace_recursive([
            'amounts' => $this->amounts($total),
            'hours' => $this->hours(),
            'zero_reasons' => [],
            'scope_conditions' => '見積条件のすべてを含みます。',
            'notes' => '各工程を積み上げた金額です。',
            'delivery_weeks' => '12',
            'extra_rates' => ['extra_page' => '30000', 'extra_post' => '3000', 'extra_revision' => '10000', 'extra_meeting' => '8000', 'monthly_maintenance' => '20000'],
            'agreements' => ['include_all_scope' => '1', 'full_scope_capable' => '1', 'no_guarantee' => '1'],
        ], $overrides);
    }

    /** 下書きを保存して確認画面を開き、提出キーを返す。 */
    protected function goToConfirm(array $input): string
    {
        $this->post('/my/estimate', $input + ['action' => 'confirm'])->assertRedirect('/my/confirm');
        $this->get('/my/confirm')->assertOk();

        return session('applicant.submit_key');
    }

    protected function submitEstimate(int $total, array $overrides = []): TestResponse
    {
        $key = $this->goToConfirm($this->estimateInput($total, $overrides));

        return $this->post('/my/submit', ['submit_key' => $key]);
    }

    protected function admin(): User
    {
        return User::query()->firstOrCreate(['email' => 'admin@example.com'], ['name' => '管理者', 'password' => 'password-123456']);
    }

    protected function enterAs(Application $application): void
    {
        $invitation = $application->invitations()->whereNull('revoked_at')->latest('id')->first();
        $this->withSession(['applicant' => ['application_id' => $application->id, 'invitation_id' => $invitation->id]]);
    }

    protected function inviteToken(Application $application): string
    {
        // テストでは平文トークンを得るため、新しい招待を発行する
        return app(InvitationService::class)->issue($application);
    }

    protected function invitationFor(string $token): Invitation
    {
        return Invitation::query()->where('token_hash', Invitation::hashToken($token))->firstOrFail();
    }
}
