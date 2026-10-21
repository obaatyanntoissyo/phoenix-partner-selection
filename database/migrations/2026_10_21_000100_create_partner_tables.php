<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 募集設定（見積条件・判定基準）。更新のたびに新しい版を追加し、既存の版は書き換えない。
        Schema::create('config_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version_no')->unique();
            $table->json('mockup_links');
            $table->json('scope_items');
            $table->json('estimate_items');
            $table->unsignedBigInteger('total_cap')->nullable();
            $table->boolean('item_caps_enabled')->default(false);
            $table->unsignedSmallInteger('invite_ttl_days')->default(14);
            $table->boolean('is_test_criteria')->default(false);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
        });

        // メールテンプレート。編集のたびに新しい版を追加する。
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->unsignedInteger('version_no');
            $table->string('subject', 255);
            $table->text('body');
            $table->boolean('enabled')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['type', 'version_no']);
        });

        Schema::create('application_counters', function (Blueprint $table) {
            $table->string('category', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_no')->default(0);
            $table->primary(['category', 'year']);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('category', 10); // p1 = 一括制作, p2 = 8341
            $table->string('status', 20)->default('received');
            $table->string('source', 20)->default('web'); // web / api / admin
            $table->string('source_event_id', 191)->nullable();
            $table->string('email_normalized', 255)->index();
            $table->string('name', 100);
            $table->string('kana', 100);
            $table->string('company', 150)->nullable();
            $table->string('email', 255);
            $table->string('phone', 30);
            $table->string('prefecture', 10);
            $table->unsignedTinyInteger('experience_years');
            $table->unsignedTinyInteger('experience_months');
            $table->string('portfolio_url', 500)->nullable();
            $table->boolean('portfolio_private')->default(false);
            $table->text('role_scope')->nullable();
            $table->unsignedSmallInteger('monthly_hours');
            $table->date('available_from');
            $table->boolean('can_full_scope')->nullable();
            $table->boolean('can_online_meeting')->nullable();
            $table->text('self_intro')->nullable();
            $table->timestamp('privacy_agreed_at')->nullable();
            $table->string('privacy_version', 50)->nullable();
            $table->foreignId('config_version_id')->nullable()->constrained('config_versions');
            $table->foreignId('linked_application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->unsignedSmallInteger('allowed_submissions')->default(1);
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();
            $table->unique(['source', 'source_event_id']);
            $table->index(['category', 'status']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 255)->nullable();
            $table->timestamp('first_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('estimate_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('amounts');
            $table->json('zero_reasons');
            $table->text('scope_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->json('agreements');
            $table->timestamps();
        });

        Schema::create('estimate_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('config_version_id')->constrained('config_versions');
            $table->json('items');
            $table->unsignedBigInteger('total');
            $table->text('scope_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->json('agreements');
            $table->timestamp('submitted_at');
            $table->unique(['application_id', 'sequence']);
        });

        Schema::create('screening_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->unique()->constrained('estimate_submissions')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('outcome', 10); // passed / failed / held
            $table->json('reason_codes');
            $table->json('criteria');
            $table->foreignId('config_version_id')->constrained('config_versions');
            $table->timestamp('decided_at');
        });

        // 面談予約（見積判定とは別に管理）
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('access_granted')->default(false);
            $table->string('booking_status', 20)->default('not_guided'); // not_guided / available / redirected / confirmed
            $table->timestamp('first_redirected_at')->nullable();
            $table->unsignedInteger('redirect_count')->default(0);
            $table->dateTime('interview_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 20)->default('unbooked'); // unbooked / booked / interviewed / considering / contracted / declined
            $table->timestamps();
        });

        Schema::create('permission_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20); // auto_grant / manual_grant / revoke
            $table->string('reason', 500)->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('email_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->text('to_address');
            $table->text('subject');
            $table->longText('body');
            $table->string('status', 20)->default('pending'); // pending / accepted / failed / dead / skipped
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('target_type', 50)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'email_jobs', 'permission_events', 'interviews', 'screening_results',
            'estimate_submissions', 'estimate_drafts', 'invitations', 'applications', 'application_counters',
            'email_templates', 'app_settings', 'config_versions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
