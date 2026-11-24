<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('job_code', 30)->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('title', 200)->index();
            $table->unsignedInteger('openings_count')->default(1);
            $table->unsignedInteger('joined_count')->default(0);
            $table->unsignedInteger('remaining_count')->default(1)->index();
            $table->string('department', 120)->nullable();
            $table->string('industry', 120)->nullable()->index();
            $table->longText('description')->nullable();
            $table->foreignId('jd_storage_id')->nullable()->constrained('storage_records')->nullOnDelete();
            $table->string('required_education', 255)->nullable();
            $table->json('required_skills')->nullable();
            $table->json('preferred_skills')->nullable();
            $table->decimal('min_experience', 4, 1)->default(0);
            $table->decimal('max_experience', 4, 1)->nullable();
            $table->decimal('min_salary', 15, 2)->nullable();
            $table->decimal('max_salary', 15, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->string('location', 180)->index();
            $table->string('country', 100)->default('India')->index();
            $table->string('work_mode', 40)->default('onsite')->index(); // onsite, hybrid, remote
            $table->string('employment_type', 40)->default('full_time'); // full_time, contract, part_time, consultant
            $table->string('notice_period_preference', 60)->default('Immediate to 30 Days');
            $table->unsignedInteger('max_notice_days')->default(60);
            $table->string('priority', 30)->default('medium')->index(); // critical, high, medium, low
            $table->date('expected_closure_date')->nullable()->index();
            $table->unsignedInteger('interview_rounds_count')->default(3);
            $table->text('client_instructions')->nullable();
            $table->text('internal_instructions')->nullable();
            $table->string('status', 50)->default('new_requirement')->index();
            // statuses: draft, new_requirement, pending_review, approved, assigned, sourcing_in_progress,
            // interview_in_progress, offer_in_progress, partially_filled, successfully_closed, on_hold, cancelled
            $table->foreignId('assigned_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('duplicated_from_id')->nullable();
            $table->json('custom_fields_data')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
            $table->index(['status', 'priority']);
        });

        Schema::create('job_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->foreignId('recruiter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('assigned_at')->useCurrent();
            $table->date('deadline_date')->nullable();
            $table->unsignedInteger('daily_sourcing_target')->default(5);
            $table->unsignedInteger('submission_target')->default(5);
            $table->string('priority', 30)->default('high');
            $table->string('recruiter_availability', 40)->default('available');
            $table->string('status', 40)->default('active')->index(); // active, reassigned, completed, paused
            $table->text('internal_instructions')->nullable();
            $table->timestamp('reassigned_at')->nullable();
            $table->timestamps();
            $table->index(['job_requirement_id', 'recruiter_id', 'status']);
            $table->index(['recruiter_id', 'status']);
        });

        Schema::create('job_screening_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->string('question', 500);
            $table->string('question_type', 40)->default('text'); // text, boolean, number, choice
            $table->json('options')->nullable();
            $table->string('expected_answer', 255)->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('interview_round_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_requirement_id')->constrained('job_requirements')->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->string('round_name', 150);
            $table->string('interview_mode', 40)->default('video'); // telephonic, video, face_to_face, technical_assessment, online_assessment
            $table->unsignedInteger('default_duration_minutes')->default(45);
            $table->string('default_interviewer_name', 150)->nullable();
            $table->string('default_interviewer_email', 180)->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->text('evaluation_instructions')->nullable();
            $table->timestamps();
            $table->unique(['job_requirement_id', 'round_number'], 'job_round_num_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_round_definitions');
        Schema::dropIfExists('job_screening_questions');
        Schema::dropIfExists('job_assignments');
        Schema::dropIfExists('job_requirements');
    }
};
