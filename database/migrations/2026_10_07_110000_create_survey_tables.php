<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Module 31 (and SRS 38: post-reunion surveys).
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->json('audience')->nullable(); // AudienceBuilder filters; ignored when event_id is set
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete(); // attendees only
            $table->boolean('is_anonymous')->default(false);
            $table->dateTime('closes_at')->nullable();
            $table->string('status', 20)->default('draft'); // draft | published | closed
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 20); // single | multiple | text | rating | nps
            $table->string('prompt', 500);
            $table->json('options')->nullable();
            $table->boolean('required')->default(true);
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            // Null for anonymous surveys: the respondent can't be traced from the answers.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // HMAC of user + survey: prevents double responses without storing who answered.
            $table->char('respondent_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['survey_id', 'respondent_hash']);
        });

        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_question_id')->constrained()->cascadeOnDelete();
            $table->json('value');

            $table->index('survey_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};
