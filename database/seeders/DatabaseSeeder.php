<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // 本番の初期データ（募集設定 第1版・テンプレート）はマイグレーションで入る。
    // 開発用のデータは `php artisan db:seed --class=DevSeeder` で投入する。
    public function run(): void {}
}
