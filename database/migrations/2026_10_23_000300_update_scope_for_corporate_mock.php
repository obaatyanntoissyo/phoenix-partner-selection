<?php

use App\Support\Defaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // モックが求人サイトから一般的なコーポレートサイトに変わったため、見積条件・見積項目を新しい版として追加する。
    // 総額の上限などの判定基準は現在の版から引き継ぎ、過去の応募に固定された版は書き換えない。
    public function up(): void
    {
        $current = DB::table('config_versions')->orderByDesc('version_no')->first();
        // 古い求人サイト向けの条件、または担当の分担・納期の回答欄を入れる前の条件のときだけ、新しい版を追加する
        if (! $current || str_contains((string) $current->scope_items, '担当の分担')) {
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
            'note' => 'モックの変更（一般的なコーポレートサイト）に合わせて見積条件・見積項目を更新',
            'created_at' => now(),
        ]);
    }

    public function down(): void {}
};
