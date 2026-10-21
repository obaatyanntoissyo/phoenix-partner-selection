<?php

use App\Support\Defaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 面談の空き枠と予約。予約は Google カレンダーへ非同期で登録する（失敗しても予約自体は確定済み）。
    public function up(): void
    {
        Schema::create('interview_slots', function (Blueprint $table) {
            $table->id();
            $table->dateTime('starts_at')->unique();
            $table->dateTime('ends_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('interview_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->nullable()->constrained('interview_slots')->nullOnDelete(); // 枠を削除しても予約履歴は残す
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active'); // active / cancelled
            // 有効な予約だけが値を持つ。一意制約で「1枠に1人」「1人に1枠」を DB で保証する
            $table->unsignedBigInteger('active_slot_id')->nullable()->unique();
            $table->unsignedBigInteger('active_application_id')->nullable()->unique();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            // Google カレンダー連携。予定IDはこちらで決めて送るため、再試行しても予定が重複しない
            $table->string('calendar_event_id', 64)->unique();
            $table->string('calendar_state', 20)->default('none'); // none / pending / synced / deleted / dead / skipped
            $table->unsignedInteger('calendar_attempts')->default(0);
            $table->timestamp('calendar_next_attempt_at')->nullable()->index();
            $table->string('calendar_error', 500)->nullable();
            $table->string('meet_url', 255)->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach (Defaults::bookingSettings() as $key => $value) {
            DB::table('app_settings')->insertOrIgnore(['key' => $key, 'value' => $value, 'updated_at' => $now]);
        }
        foreach (Defaults::bookingTemplates() as $type => $tpl) {
            if (! DB::table('email_templates')->where('type', $type)->exists()) {
                DB::table('email_templates')->insert([
                    'type' => $type, 'version_no' => 1, 'subject' => $tpl['subject'], 'body' => $tpl['body'], 'enabled' => $tpl['enabled'], 'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_bookings');
        Schema::dropIfExists('interview_slots');
        DB::table('app_settings')->whereIn('key', array_keys(Defaults::bookingSettings()))->delete();
        DB::table('email_templates')->whereIn('type', array_keys(Defaults::bookingTemplates()))->delete();
    }
};
