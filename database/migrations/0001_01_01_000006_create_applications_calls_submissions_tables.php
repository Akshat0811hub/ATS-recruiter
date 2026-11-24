<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_code', 30)->unique();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pipeline_stage', 50)->default('sourced')->index();
            // Main pipeline stages:
            // sourced, contacted, interested, screened, submitted, client_shortlisted,
            // interview_scheduled, interview_in_progress, selected, offer_released,
            // offer_accepted, joining_pending, joined
            // Alternative states:
            // not_interested, rejected, on_hold, dropped_out, did_not_join
            $table->timestamp('stage_updated_at')->useCurrent()->index();
            $table->decimal('applied_ctc', 15, 2)->nullable();
            $table->decimal('expected_ctc', 15, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->unsignedInteger('notice_period_days')->nullable();
            $table->unsignedSmallInteger('current_interview_round')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('rejection_reason', 255)->nullable();
            $table->string('dropout_reason', 255)->nullable();
            $table->text('stage_remarks')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['candidate_id', 'job_requirement_id'], 'cand_job_app_unique');
            $table->index(['job_requirement_id', 'pipeline_stage']);
            $table->index(['client_id', 'pipeline_stage']);
            $table->index(['recruiter_id', 'pipeline_stage']);
        });

        Schema::create('screening_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_application_id')->constrained('candidate_applications')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('education_match')->default(true);
            $table->boolean('experience_match')->default(true);
            $table->boolean('skills_match')->default(true);
            $table->boolean('salary_compatible')->default(true);
            $table->boolean('location_compatible')->default(true);
            $table->boolean('notice_period_compatible')->default(true);
            $table->string('candidate_interest', 50)->default('High');
            $table->unsignedTinyInteger('communication_rating')->default(4); // 1 to 5
            $table->json('question_answers')->nullable();
            $table->string('screening_status', 40)->default('passed')->index(); // passed, on_hold, not_eligible
            $table->text('assessment_summary');
            $table->text('recruiter_recommendation')->nullable();
            $table->timestamp('screened_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('job_requirement_id')->nullable()->constrained('job_requirements')->nullOnDelete();
            $table->foreignId('candidate_application_id')->nullable()->constrained('candidate_applications')->nullOnDelete();
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('called_at')->useCurrent()->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('outcome', 50)->index();
            // interested, not_interested, not_answered, busy, switched_off,
            // call_back_requested, incorrect_number, not_eligible, screening_completed, follow_up_required
            $table->string('candidate_interest', 50)->nullable();
            $table->text('discussion_summary');
            $table->json('screening_qa')->nullable();
            $table->text('eligibility_remarks')->nullable();
            $table->decimal('current_ctc', 15, 2)->nullable();
            $table->decimal('expected_ctc', 15, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->unsignedInteger('notice_period_days')->nullable();
            $table->string('interview_availability', 255)->nullable();
            $table->string('next_action', 120)->nullable();
            $table->timestamp('follow_up_at')->nullable()->index();
            $table->timestamps();
            $table->index(['recruiter_id', 'called_at']);
        });

        Schema::create('communication_records', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 40)->index(); // phone, email, portal, whatsapp_optional
            $table->string('direction', 20)->default('outbound'); // outbound, inbound
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('job_requirement_id')->nullable()->constrained('job_requirements')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 255)->nullable();
            $table->text('summary');
            $table->timestamp('communicated_at')->useCurrent()->index();
            $table->timestamps();
        });

        Schema::create('client_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_code', 30)->unique();
            $table->foreignId('candidate_application_id')->constrained('candidate_applications')->cascadeOnDelete();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resume_version_id')->nullable()->constrained('resume_versions')->nullOnDelete();
            // Immutable snapshot fields captured at submission time
            $table->string('snapshot_candidate_name', 180);
            $table->string('snapshot_designation', 160)->nullable();
            $table->string('snapshot_employer', 180)->nullable();
            $table->decimal('snapshot_relevant_experience', 4, 1)->default(0);
            $table->decimal('snapshot_total_experience', 4, 1)->default(0);
            $table->decimal('snapshot_current_ctc', 15, 2)->nullable();
            $table->decimal('snapshot_expected_ctc', 15, 2)->nullable();
            $table->string('snapshot_currency', 10)->default('INR');
            $table->unsignedInteger('snapshot_notice_period_days')->default(30);
            $table->string('snapshot_location', 150)->nullable();
            $table->text('screening_summary');
            $table->string('candidate_availability', 255)->nullable();
            $table->text('recruiter_recommendation')->nullable();
            $table->text('client_visible_notes')->nullable();
            $table->string('client_decision', 50)->default('pending_review')->index();
            // pending_review, shortlisted, rejected, on_hold, additional_info_required, interview_requested
            $table->string('rejection_reason', 255)->nullable();
            $table->text('client_feedback_notes')->nullable();
            $table->string('preferred_interview_slots', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->useCurrent()->index();
            $table->timestamp('decided_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['candidate_id', 'job_requirement_id'], 'sub_cand_job_unique');
            $table->index(['client_id', 'client_decision']);
            $table->index(['recruiter_id', 'submitted_at']);
        });

        Schema::create('client_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_submission_id')->constrained('client_submissions')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 50);
            $table->string('rejection_reason', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('recorded_by_recruiter')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_feedback');
        Schema::dropIfExists('client_submissions');
        Schema::dropIfExists('communication_records');
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('screening_records');
        Schema::dropIfExists('candidate_applications');
    }
};
