<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_drafts', function (Blueprint $table) {
            $table->json('hours')->nullable()->after('amounts');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_drafts', function (Blueprint $table) {
            $table->dropColumn('hours');
        });
    }
};
