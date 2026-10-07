<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 48-49.
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('subject', 200);
            $table->text('body'); // Markdown, rendered safely
            $table->json('channels'); // ["email","in_app"]
            $table->json('audience'); // segment filters, see AudienceBuilder
            $table->string('status', 20)->default('draft'); // draft | scheduled | sending | sent | cancelled
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('emails_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('emailed')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['campaign_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
