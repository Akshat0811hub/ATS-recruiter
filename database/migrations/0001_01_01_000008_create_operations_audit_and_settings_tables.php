<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('task_type', 50)->default('follow_up')->index();
            // call_candidate, request_resume, screen_candidate, submit_profile,
            // request_client_feedback, confirm_interview, collect_interview_results,
            // follow_up_on_offer, confirm_joining, post_joining_follow_up
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('job_requirement_id')->nullable()->constrained('job_requirements')->nullOnDelete();
            $table->foreignId('candidate_application_id')->nullable()->constrained('candidate_applications')->nullOnDelete();
            $table->string('priority', 30)->default('medium')->index(); // critical, high, medium, low
            $table->date('due_date')->index();
            $table->string('due_time', 20)->nullable();
            $table->string('status', 40)->default('pending')->index(); // pending, in_progress, completed, cancelled
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_pattern', 40)->nullable();
            $table->string('auto_trigger_key', 120)->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['assigned_to', 'status', 'due_date']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->string('category', 60)->default('general')->index();
            $table->string('action_url', 500)->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 150);
            $table->string('category', 60)->default('recruitment');
            $table->string('subject', 255);
            $table->longText('body_html');
            $table->json('available_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('template_slug', 80)->nullable()->index();
            $table->string('recipient_email', 180)->index();
            $table->string('recipient_name', 150)->nullable();
            $table->string('subject', 255);
            $table->longText('body_preview')->nullable();
            $table->string('delivery_status', 40)->default('queued')->index(); // queued, smtp_accepted, failed, simulated_local
            $table->text('smtp_response')->nullable();
            $table->string('related_type', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('status_history', function (Blueprint $table) {
            $table->id();
            $table->string('trackable_type', 100);
            $table->unsignedBigInteger('trackable_id');
            $table->string('from_status', 60)->nullable();
            $table->string('to_status', 60)->index();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['trackable_type', 'trackable_id']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('event', 80)->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->boolean('is_client_visible')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 150)->nullable();
            $table->string('user_role', 50)->nullable();
            $table->string('action', 100)->index();
            $table->string('auditable_type', 100)->index();
            $table->unsignedBigInteger('auditable_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('import_export_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type', 20)->index(); // import, export
            $table->string('entity_type', 60)->index(); // candidates, jobs, clients, report
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name', 255);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('error_details')->nullable();
            $table->string('status', 40)->default('completed')->index(); // processing, completed, completed_with_warnings, failed
            $table->timestamps();
        });

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('backup_code', 40)->unique();
            $table->string('backup_type', 40)->default('full'); // full, incremental, database_only
            $table->string('destination', 80)->default('local_vault'); // local_vault, external_s3, external_sftp
            $table->string('archive_path', 500)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('sha256_checksum', 64)->nullable();
            $table->boolean('is_encrypted')->default(true);
            $table->boolean('external_configured')->default(false);
            $table->boolean('integrity_verified')->default(false);
            $table->string('status', 40)->default('completed')->index(); // running, completed, failed, restored
            $table->text('error_message')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60)->index();
            $table->string('key', 100)->unique();
            $table->longText('value')->nullable();
            $table->string('type', 30)->default('string'); // string, integer, boolean, json
            $table->string('label', 150)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40)->index(); // candidate, job_requirement, client
            $table->string('field_key', 80);
            $table->string('field_label', 150);
            $table->string('field_type', 40)->default('text'); // text, number, date, select, boolean
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
            $table->unique(['entity_type', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('import_export_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('status_history');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('tasks');
    }
};
