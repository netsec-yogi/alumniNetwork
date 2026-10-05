<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 80. Append-only: the application never updates or deletes rows
        // except through the retention prune command.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('module', 40);
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('request_id', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['module', 'action']);
            $table->index(['entity_type', 'entity_id']);
        });

        // SRS 110. One row per acceptance, so earlier versions stay provable.
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('consent_type', 40);
            $table->string('version', 20);
            $table->boolean('granted')->default(true);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'consent_type']);
        });

        // SRS 52: the foundation for engagement analytics. Scores are always
        // derived from these rows, never stored in place of them.
        Schema::create('engagement_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_profile_id')->constrained()->cascadeOnDelete();
            $table->string('activity_type', 40);
            $table->date('activity_date');
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            // CASE engagement mode (SRS 55): philanthropic | volunteer | experiential | communication
            $table->string('engagement_mode', 20);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['alumni_profile_id', 'activity_date']);
            $table->index(['activity_type', 'activity_date']);
            $table->index(['engagement_mode', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_activities');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('audit_logs');
    }
};
