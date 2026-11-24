<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_records', function (Blueprint $table) {
            $table->id();
            $table->string('interview_code', 30)->unique();
            $table->foreignId('candidate_application_id')->constrained('candidate_applications')->cascadeOnDelete();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('interview_round_definition_id')->nullable()->constrained('interview_round_definitions')->nullOnDelete();
            $table->unsignedSmallInteger('round_number')->default(1);
            $table->string('round_name', 150);
            $table->string('interviewer_name', 150)->nullable();
            $table->string('interviewer_email', 180)->nullable();
            $table->string('interviewer_phone', 40)->nullable();
            $table->date('interview_date')->nullable()->index();
            $table->string('interview_time', 20)->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->unsignedInteger('duration_minutes')->default(45);
            $table->string('mode', 40)->default('video')->index();
            // telephonic, video, face_to_face, technical_assessment, online_assessment
            $table->string('meeting_link', 500)->nullable();
            $table->string('physical_location', 300)->nullable();
            $table->string('candidate_availability', 255)->nullable();
            $table->string('client_availability', 255)->nullable();
            $table->boolean('candidate_confirmed')->default(false);
            $table->boolean('client_confirmed')->default(false);
            $table->text('interview_instructions')->nullable();
            $table->text('recruiter_remarks')->nullable();
            $table->string('status', 40)->default('not_started')->index();
            // not_started, pending, scheduled, completed, passed, failed, on_hold,
            // rescheduled, no_show, cancelled, skipped_with_authorisation
            $table->string('result', 40)->nullable()->index(); // passed, failed, on_hold, pending
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback_summary')->nullable();
            $table->string('next_action', 150)->nullable();
            $table->unsignedSmallInteger('attempts_count')->default(1);
            $table->boolean('is_overridden')->default(false);
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['candidate_application_id', 'round_number'], 'app_round_num_unique');
            $table->index(['client_id', 'interview_date']);
            $table->index(['candidate_id', 'scheduled_at']);
        });

        Schema::create('interview_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_record_id')->constrained('interview_records')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('mode', 40)->default('video');
            $table->string('interviewer_name', 150)->nullable();
            $table->string('status', 40)->default('scheduled'); // scheduled, rescheduled, completed, no_show, cancelled
            $table->string('reason', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('interview_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_record_id')->constrained('interview_records')->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evaluator_name', 150)->nullable();
            $table->decimal('technical_score', 4, 1)->nullable();
            $table->decimal('communication_score', 4, 1)->nullable();
            $table->decimal('role_fit_score', 4, 1)->nullable();
            $table->decimal('overall_score', 4, 1)->nullable();
            $table->string('result', 40); // passed, failed, on_hold
            $table->text('strengths')->nullable();
            $table->text('areas_of_concern')->nullable();
            $table->text('remarks');
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('offer_code', 30)->unique();
            $table->foreignId('candidate_application_id')->unique()->constrained('candidate_applications')->cascadeOnDelete();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('selection_date')->index();
            $table->string('offered_designation', 160);
            $table->decimal('offered_salary', 15, 2);
            $table->decimal('negotiated_salary', 15, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->date('offer_release_date')->nullable()->index();
            $table->foreignId('offer_letter_storage_id')->nullable()->constrained('storage_records')->nullOnDelete();
            $table->date('expected_joining_date')->nullable()->index();
            $table->date('candidate_acceptance_date')->nullable();
            $table->string('status', 40)->default('selected')->index();
            // selected, offer_pending, offer_released, under_negotiation,
            // offer_accepted, offer_rejected, candidate_backed_out, offer_withdrawn
            $table->text('negotiation_notes')->nullable();
            $table->string('decline_or_backout_reason', 255)->nullable();
            $table->date('next_followup_date')->nullable()->index();
            $table->timestamps();
            $table->index(['client_id', 'status']);
            $table->index(['recruiter_id', 'status']);
        });

        Schema::create('offer_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->unsignedSmallInteger('revision_number')->default(1);
            $table->string('offered_designation', 160);
            $table->decimal('offered_salary', 15, 2);
            $table->string('currency', 10)->default('INR');
            $table->date('expected_joining_date')->nullable();
            $table->text('revision_reason')->nullable();
            $table->foreignId('revised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('joining_records', function (Blueprint $table) {
            $table->id();
            $table->string('joining_code', 30)->unique();
            $table->foreignId('candidate_application_id')->unique()->constrained('candidate_applications')->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expected_joining_date')->index();
            $table->date('actual_joining_date')->nullable()->index();
            $table->boolean('candidate_confirmed')->default(false);
            $table->boolean('client_confirmed')->default(false);
            $table->string('status', 40)->default('joining_pending')->index();
            // joining_pending, joined, joining_delayed, did_not_join,
            // left_after_joining, replacement_required, placement_completed
            $table->foreignId('joining_document_storage_id')->nullable()->constrained('storage_records')->nullOnDelete();
            $table->decimal('final_salary', 15, 2)->nullable();
            $table->decimal('placement_value', 15, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->unsignedInteger('replacement_period_days')->default(90);
            $table->date('replacement_expiry_date')->nullable()->index();
            $table->date('next_checkin_date')->nullable()->index();
            $table->json('post_joining_checkins')->nullable();
            $table->boolean('vacancy_counted')->default(false);
            $table->string('non_joining_reason', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->string('placement_code', 30)->unique();
            $table->foreignId('joining_record_id')->unique()->constrained('joining_records')->cascadeOnDelete();
            $table->foreignId('candidate_application_id')->unique()->constrained('candidate_applications')->cascadeOnDelete();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('joined_date')->index();
            $table->decimal('final_ctc', 15, 2);
            $table->decimal('billable_fee', 15, 2)->default(0);
            $table->string('currency', 10)->default('INR');
            $table->date('warranty_end_date')->index();
            $table->string('status', 40)->default('active_warranty')->index();
            // active_warranty, placement_completed, replacement_triggered
            $table->timestamps();
        });

        Schema::create('replacement_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_id')->constrained('placements')->cascadeOnDelete();
            $table->foreignId('joining_record_id')->constrained('joining_records')->cascadeOnDelete();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('original_candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('replacement_candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->date('exit_date');
            $table->string('exit_reason', 255);
            $table->boolean('within_replacement_period')->default(true);
            $table->boolean('vacancy_reopened')->default(false);
            $table->string('status', 40)->default('open')->index(); // open, in_progress, fulfilled, waived
            $table->foreignId('authorised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacement_records');
        Schema::dropIfExists('placements');
        Schema::dropIfExists('joining_records');
        Schema::dropIfExists('offer_revisions');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('interview_feedback');
        Schema::dropIfExists('interview_attempts');
        Schema::dropIfExists('interview_records');
    }
};
