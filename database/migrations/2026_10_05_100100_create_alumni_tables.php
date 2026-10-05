<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | The institute's own record of who graduated -- imported from the
         | academic section, never edited by alumni. Registrations are matched
         | against it for automatic verification (SRS 20).
         */
        Schema::create('alumni_records', function (Blueprint $table) {
            $table->id();
            $table->string('roll_number', 30)->unique();
            $table->string('name');
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('admission_year')->nullable();
            $table->unsignedSmallInteger('graduation_year');
            $table->date('date_of_birth')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->index(['programme_id', 'graduation_year']);
        });

        Schema::create('alumni_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('alumni_record_id')->nullable()->unique()->constrained()->nullOnDelete();

            // Personal
            $table->string('preferred_name')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();

            // Academic. "Batch" is the graduation year; it is not stored twice.
            $table->string('roll_number', 30)->index();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->string('specialization')->nullable();
            $table->unsignedSmallInteger('admission_year')->nullable();
            $table->unsignedSmallInteger('graduation_year')->index();

            // Professional
            $table->string('company')->nullable()->index();
            $table->string('designation')->nullable();
            $table->string('industry', 100)->nullable()->index();
            $table->string('city', 100)->nullable()->index();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable()->index();
            $table->string('linkedin_url')->nullable();
            $table->string('website_url')->nullable();

            // Engagement interests (SRS 19): mentor, speaker, recruiter, ...
            $table->json('interests')->nullable();
            // Per-field visibility (SRS 22): {"email": "alumni", ...}
            $table->json('visibility')->nullable();

            $table->string('verification_status', 20)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['verification_status', 'programme_id', 'graduation_year'], 'alumni_profiles_directory_index');
        });

        /*
         | Every verification attempt is kept, so the history of who approved
         | or rejected a claim -- and why -- survives re-submission.
         */
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->string('method', 20); // auto_match | manual
            $table->foreignId('matched_record_id')->nullable()->constrained('alumni_records')->nullOnDelete();
            $table->string('evidence_path')->nullable(); // private disk only
            $table->text('applicant_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
        Schema::dropIfExists('alumni_profiles');
        Schema::dropIfExists('alumni_records');
    }
};
