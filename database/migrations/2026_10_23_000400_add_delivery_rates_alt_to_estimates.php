<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 納期（週）、追加の単価、別パターンの提案を、下書きと提出内容の両方に保存する。
    public function up(): void
    {
        foreach (['estimate_drafts', 'estimate_submissions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedSmallInteger('delivery_weeks')->nullable();
                $table->json('extra_rates')->nullable();
                $table->text('alt_proposal')->nullable();
                $table->unsignedBigInteger('alt_total')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['estimate_drafts', 'estimate_submissions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['delivery_weeks', 'extra_rates', 'alt_proposal', 'alt_total']);
            });
        }
    }
};
