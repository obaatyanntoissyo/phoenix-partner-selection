<?php

use App\Support\Defaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // 初期の募集設定・テンプレート・設定キー。予約URLなどの未確定値は未設定（null）のまま入れる。
    public function up(): void
    {
        $now = now();

        DB::table('config_versions')->insert([
            'version_no' => 1,
            'mockup_links' => json_encode(Defaults::mockupLinks(), JSON_UNESCAPED_UNICODE),
            'scope_items' => json_encode(Defaults::scopeItems(), JSON_UNESCAPED_UNICODE),
            'estimate_items' => json_encode(Defaults::estimateItems(), JSON_UNESCAPED_UNICODE),
            'total_cap' => 800_000, // 2026-10-21 ご依頼者の指定による暫定の総額上限（税別）。管理画面で変更可
            'item_caps_enabled' => false,
            'invite_ttl_days' => 14,
            'is_test_criteria' => false,
            'note' => '初期版（総額上限 800,000円・暫定）',
            'created_at' => $now,
        ]);

        foreach (Defaults::settings() as $key => $value) {
            DB::table('app_settings')->insert(['key' => $key, 'value' => $value, 'updated_at' => $now]);
        }

        foreach (Defaults::templates() as $type => $tpl) {
            DB::table('email_templates')->insert([
                'type' => $type,
                'version_no' => 1,
                'subject' => $tpl['subject'],
                'body' => $tpl['body'],
                'enabled' => $tpl['enabled'],
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('email_templates')->delete();
        DB::table('app_settings')->delete();
        DB::table('config_versions')->delete();
    }
};
