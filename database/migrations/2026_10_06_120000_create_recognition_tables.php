<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 39: submitted -> approved/rejected -> published.
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_profile_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('achieved_on')->nullable();
            $table->string('link_url', 500)->nullable();
            $table->foreignUlid('image_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->string('status', 20)->default('submitted'); // submitted | published | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'achieved_on']);
        });

        // SRS 40.
        Schema::create('distinguished_alumni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->unsignedSmallInteger('award_year');
            $table->text('citation');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // SRS 41.
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('type', 20); // article | interview | video | photo_story
            $table->string('title', 200);
            $table->string('excerpt', 400)->nullable();
            $table->longText('body'); // Markdown, rendered safely on output
            $table->foreignUlid('cover_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->string('video_url', 500)->nullable();
            $table->foreignId('alumni_profile_id')->nullable()->constrained()->nullOnDelete(); // featured alumnus
            // Tags (SRS 41): batch, programme, industry, location.
            $table->unsignedSmallInteger('batch_year')->nullable()->index();
            $table->foreignId('programme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('industry', 100)->nullable();
            $table->string('location', 100)->nullable();
            $table->string('status', 20)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
        Schema::dropIfExists('distinguished_alumni');
        Schema::dropIfExists('achievements');
    }
};
