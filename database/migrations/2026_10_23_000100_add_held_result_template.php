<?php

use App\Support\Defaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (Defaults::resultTemplates() as $type => $tpl) {
            if (! DB::table('email_templates')->where('type', $type)->exists()) {
                DB::table('email_templates')->insert([
                    'type' => $type, 'version_no' => 1, 'subject' => $tpl['subject'], 'body' => $tpl['body'], 'enabled' => $tpl['enabled'], 'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('email_templates')->whereIn('type', array_keys(Defaults::resultTemplates()))->delete();
    }
};
