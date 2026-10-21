<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 開発環境のテストデータ（管理者・テスト予約画面・通知先）。本番では実行できない。
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevSeeder は本番では実行できません。');
        }

        $password = Str::password(16, symbols: false);
        User::query()->updateOrCreate(['email' => 'admin@example.invalid'], ['name' => '開発用管理者', 'password' => $password]);
        $this->command?->warn("開発用管理者: admin@example.invalid / {$password}（この表示は一度だけです）");

        AppSetting::put('booking_url', rtrim(config('app.url'), '/').'/dev/booking');
        AppSetting::put('notify_emails', 'recruit-team@example.invalid');
    }
}
