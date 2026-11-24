<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_code', 30)->unique();
            $table->string('full_name', 180)->index();
            $table->string('mobile_number', 40);
            $table->string('normalized_mobile', 30)->index();
            $table->string('alternate_number', 40)->nullable();
            $table->string('normalized_alternate', 30)->nullable()->index();
            $table->string('email', 180)->nullable();
            $table->string('normalized_email', 180)->nullable()->index();
            $table->string('current_location', 150)->nullable()->index();
            $table->string('preferred_location', 180)->nullable();
            $table->string('country', 100)->default('India')->index();
            $table->string('current_employer', 180)->nullable()->index();
            $table->string('current_designation', 160)->nullable()->index();
            $table->decimal('total_experience', 4, 1)->default(0)->index();
            $table->decimal('relevant_experience', 4, 1)->default(0);
            $table->string('qualifications', 255)->nullable();
            $table->string('industry', 120)->nullable()->index();
            $table->decimal('current_ctc', 15, 2)->nullable();
            $table->decimal('expected_ctc', 15, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->unsignedInteger('notice_period_days')->default(30)->index();
            $table->boolean('is_serving_notice')->default(false);
            $table->date('last_working_date')->nullable();
            $table->string('reason_for_change', 255)->nullable();
            $table->string('employment_preference', 40)->default('full_time');
            $table->string('work_mode_preference', 40)->default('any');
            $table->string('source', 80)->default('Direct Sourcing')->index();
            $table->foreignId('assigned_recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->string('primary_resume_checksum', 64)->nullable()->index();
            $table->string('consent_status', 40)->default('granted')->index(); // granted, pending, withdrawn, erasure_requested
            $table->timestamp('consent_recorded_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable()->index();
            $table->timestamp('next_follow_up_at')->nullable()->index();
            $table->string('availability_status', 60)->default('Active / Exploring');
            $table->string('current_stage', 50)->default('sourced')->index();
            $table->text('remarks')->nullable();
            $table->json('custom_fields_data')->nullable();
            $table->unsignedBigInteger('merged_into_candidate_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_recruiter_id', 'current_stage']);
            $table->index(['created_at']);
        });

        Schema::create('candidate_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('contact_type', 40); // mobile, email, linkedin, whatsapp
            $table->string('contact_value', 200);
            $table->string('normalized_value', 200)->index();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('skill_name', 120)->index();
            $table->decimal('experience_years', 4, 1)->nullable();
            $table->string('proficiency', 40)->default('intermediate');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->unique(['candidate_id', 'skill_name']);
        });

        Schema::create('resume_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('storage_record_id')->constrained('storage_records')->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('sha256_checksum', 64)->index();
            $table->boolean('is_primary')->default(true)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('extracted_summary')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['candidate_id', 'version_number']);
        });

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('storage_record_id')->constrained('storage_records')->cascadeOnDelete();
            $table->string('document_type', 60)->index(); // offer_letter, relieving_letter, payslip, id_verification, portfolio, other
            $table->string('title', 180);
            $table->boolean('is_client_visible')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidate_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->unsignedBigInteger('job_requirement_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->string('note_type', 40)->default('internal'); // internal, screening, client_visible, warning
            $table->boolean('is_client_visible')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_notes');
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('resume_versions');
        Schema::dropIfExists('candidate_skills');
        Schema::dropIfExists('candidate_contacts');
        Schema::dropIfExists('candidates');
    }
};
