<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 29.
        Schema::create('mentor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_accepting')->default(true)->index();
            $table->json('categories');
            $table->json('expertise')->nullable();
            $table->text('bio')->nullable();
            $table->string('preferred_mentee', 20)->default('both'); // students | alumni | both
            $table->unsignedTinyInteger('max_mentees')->default(3);
            $table->string('availability', 255)->nullable();
            $table->string('preferred_mode', 20)->default('video');
            $table->timestamps();
        });

        Schema::create('mentorship_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentee_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 30);
            $table->text('goals');
            $table->string('status', 20)->default('pending'); // pending | accepted | declined | completed | cancelled
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->string('mentor_note', 500)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['mentor_id', 'status']);
            $table->index(['mentee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_requests');
        Schema::dropIfExists('mentor_profiles');
    }
};
