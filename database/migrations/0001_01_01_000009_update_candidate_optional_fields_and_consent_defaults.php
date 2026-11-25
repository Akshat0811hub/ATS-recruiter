<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->decimal('total_experience', 4, 1)->nullable()->default(0)->change();
            $table->decimal('relevant_experience', 4, 1)->nullable()->default(0)->change();
            $table->unsignedInteger('notice_period_days')->nullable()->default(null)->change();
            $table->string('consent_status', 40)->default('pending')->change();
            $table->string('availability_status', 60)->nullable()->default('Active / Exploring')->change();
        });

        Schema::table('client_submissions', function (Blueprint $table) {
            $table->decimal('snapshot_relevant_experience', 4, 1)->nullable()->default(0)->change();
            $table->decimal('snapshot_total_experience', 4, 1)->nullable()->default(0)->change();
            $table->unsignedInteger('snapshot_notice_period_days')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('client_submissions', function (Blueprint $table) {
            $table->decimal('snapshot_relevant_experience', 4, 1)->default(0)->change();
            $table->decimal('snapshot_total_experience', 4, 1)->default(0)->change();
            $table->unsignedInteger('snapshot_notice_period_days')->default(30)->change();
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->decimal('total_experience', 4, 1)->default(0)->change();
            $table->decimal('relevant_experience', 4, 1)->default(0)->change();
            $table->unsignedInteger('notice_period_days')->default(30)->change();
            $table->string('consent_status', 40)->default('granted')->change();
            $table->string('availability_status', 60)->default('Active / Exploring')->change();
        });
    }
};
