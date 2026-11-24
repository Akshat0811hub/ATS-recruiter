<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->string('specialization', 120)->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('daily_sourcing_target')->default(50);
            $table->unsignedInteger('daily_call_target')->default(150);
            $table->unsignedInteger('daily_submission_target')->default(20);
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('team_role', 40)->default('recruiter'); // manager, team_leader, recruiter
            $table->timestamp('joined_at')->useCurrent();
            $table->boolean('is_active')->default(true);
            $table->unique(['team_id', 'user_id']);
            $table->timestamps();
        });

        Schema::create('storage_records', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 40)->default('local');
            $table->string('category', 50)->index(); // resume, jd, agreement, offer_letter, joining_doc, backup, other
            $table->string('file_path', 500);
            $table->string('secure_filename', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes')->default(0)->index();
            $table->string('sha256_checksum', 64)->index();
            $table->string('owner_type', 100)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_quarantined')->default(false);
            $table->string('scan_status', 30)->default('verified'); // verified, quarantined, failed
            $table->timestamps();
            $table->softDeletes();
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_code', 30)->unique();
            $table->string('company_name', 200)->index();
            $table->string('industry', 120)->nullable()->index();
            $table->string('website', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('country', 100)->default('India')->index();
            $table->json('locations')->nullable();
            $table->string('primary_hr_name', 150);
            $table->string('primary_hr_designation', 120)->nullable();
            $table->string('primary_hr_phone', 40);
            $table->string('primary_hr_email', 180)->index();
            $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('status', 40)->default('active')->index(); // active, onboarding, on_hold, inactive
            $table->foreignId('agreement_storage_id')->nullable()->constrained('storage_records')->nullOnDelete();
            $table->text('recruitment_terms')->nullable();
            $table->decimal('placement_fee_percentage', 5, 2)->default(8.33);
            $table->decimal('flat_placement_fee', 14, 2)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->unsignedInteger('replacement_period_days')->default(90);
            $table->unsignedInteger('payment_terms_days')->default(30);
            $table->text('replacement_policy')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('designation', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 180)->index();
            $table->string('department', 120)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_access_portal')->default(true);
            $table->boolean('receives_notifications')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('storage_records');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
