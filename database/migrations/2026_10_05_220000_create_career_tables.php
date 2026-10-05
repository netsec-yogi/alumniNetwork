<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 32. Jobs and internships share one table; `type` tells them apart.
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // job | internship
            $table->string('title', 160);
            $table->string('organization', 160);
            $table->string('location', 160)->nullable();
            $table->string('work_mode', 20); // onsite | remote | hybrid
            $table->string('employment_type', 20); // full_time | part_time | contract | internship
            $table->unsignedTinyInteger('experience_min')->nullable();
            $table->unsignedTinyInteger('experience_max')->nullable();
            $table->json('skills')->nullable();
            $table->string('compensation', 100)->nullable();
            $table->text('description');
            $table->string('apply_url', 255)->nullable();
            $table->string('apply_email', 255)->nullable();
            $table->date('deadline')->nullable();
            $table->boolean('referral_available')->default(false);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | closed
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'deadline']);
            $table->index(['type', 'status']);
        });

        // SRS 33.
        Schema::create('job_referral_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->string('profile_url', 255)->nullable();
            $table->string('status', 20)->default('pending'); // pending | accepted | declined
            $table->string('response_note', 500)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['job_posting_id', 'requester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_referral_requests');
        Schema::dropIfExists('job_postings');
    }
};
