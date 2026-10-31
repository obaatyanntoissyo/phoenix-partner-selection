<?php

use App\Support\Defaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // 2026-10-31 の確定版に合わせ、見積項目の名称・順序・説明と見積条件を新しい版として追加する。
    // 総額の上限などの判定基準は現在の版から引き継ぎ、過去の応募に固定された版は書き換えない。
    public function up(): void
    {
        $current = DB::table('config_versions')->orderByDesc('version_no')->first();
        if (! $current || str_contains((string) $current->scope_items, '項目間の重複計上を避けるルール')) {
            return;
        }

        DB::table('config_versions')->insert([
            'version_no' => $current->version_no + 1,
            'mockup_links' => $current->mockup_links,
            'scope_items' => json_encode(Defaults::scopeItems(), JSON_UNESCAPED_UNICODE),
            'estimate_items' => json_encode(Defaults::estimateItems(), JSON_UNESCAPED_UNICODE),
            'total_cap' => $current->total_cap,
            'item_caps_enabled' => $current->item_caps_enabled,
            'invite_ttl_days' => $current->invite_ttl_days,
            'is_test_criteria' => $current->is_test_criteria,
            'note' => '見積項目を確定版の10項目（名称・順序・説明）に統一し、新規一式制作の前提と重複計上のルールを追加',
            'created_at' => now(),
        ]);
    }

    public function down(): void {}
};
