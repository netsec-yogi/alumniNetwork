<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 34.
        Schema::create('startups', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 140)->unique();
            $table->string('name', 140);
            $table->string('tagline', 200)->nullable();
            $table->text('description');
            $table->string('industry', 100);
            $table->string('website_url', 255)->nullable();
            $table->string('location', 120)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('funding_stage', 30); // bootstrapped | pre_seed | seed | series_a | series_b_plus | profitable | acquired
            $table->boolean('is_hiring')->default(false);
            $table->foreignUlid('logo_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->boolean('is_hidden')->default(false); // set by content managers
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['industry', 'funding_stage']);
        });

        Schema::create('startup_founders', function (Blueprint $table) {
            $table->foreignId('startup_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alumni_profile_id')->constrained()->cascadeOnDelete();
            $table->string('role', 80)->nullable();
            $table->primary(['startup_id', 'alumni_profile_id']);
        });

        // SRS 42.
        Schema::create('research_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30); // project | collaboration | guest_lecture | consultancy | internship
            $table->string('title', 200);
            $table->text('description');
            $table->json('areas')->nullable();
            $table->string('organization', 160)->nullable();
            $table->date('closes_on')->nullable();
            $table->string('status', 20)->default('open'); // open | closed
            $table->foreignId('posted_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
        });

        Schema::create('research_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->unique(['research_opportunity_id', 'user_id']);
        });

        // SRS 43.
        Schema::create('speaker_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(true)->index();
            $table->json('topics');
            $table->json('formats'); // talk | workshop | panel | fireside | webinar
            $table->text('bio')->nullable();
            $table->string('languages', 120)->nullable();
            $table->boolean('remote')->default(true);
            $table->boolean('in_person')->default(false);
            $table->timestamps();
        });

        Schema::create('speaker_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('speaker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('details');
            $table->string('format', 20);
            $table->date('proposed_date')->nullable();
            $table->string('status', 20)->default('pending'); // pending | accepted | declined | delivered | cancelled
            $table->string('response_note', 500)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['speaker_id', 'status']);
            $table->index(['inviter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaker_invitations');
        Schema::dropIfExists('speaker_profiles');
        Schema::dropIfExists('research_interests');
        Schema::dropIfExists('research_opportunities');
        Schema::dropIfExists('startup_founders');
        Schema::dropIfExists('startups');
    }
};
