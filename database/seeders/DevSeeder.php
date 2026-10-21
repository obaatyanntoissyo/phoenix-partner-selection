<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\ConfigVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 開発環境のテストデータ。本番では実行できない。
 * 判定基準は「テスト用の仮基準」として明示し、本番の基準額としては使わない。
 */
class DevSeeder extends Seeder
{
    public const TEST_TOTAL_CAP = 1_500_000;

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevSeeder は本番では実行できません。');
        }

        $password = Str::password(16, symbols: false);
        User::query()->updateOrCreate(['email' => 'admin@example.invalid'], ['name' => '開発用管理者', 'password' => $password]);
        $this->command?->warn("開発用管理者: admin@example.invalid / {$password}（この表示は一度だけです）");

        $current = ConfigVersion::current();
        ConfigVersion::create([
            'version_no' => $current->version_no + 1,
            'mockup_links' => $current->mockup_links,
            'scope_items' => $current->scope_items,
            'estimate_items' => $current->estimate_items,
            'total_cap' => self::TEST_TOTAL_CAP,
            'item_caps_enabled' => false,
            'invite_ttl_days' => 14,
            'is_test_criteria' => true,
            'note' => 'テスト用の仮基準（本番値ではありません）',
        ]);

        AppSetting::put('booking_url', rtrim(config('app.url'), '/').'/dev/booking');
        AppSetting::put('notify_emails', 'recruit-team@example.invalid');
    }
}
