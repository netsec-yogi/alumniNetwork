<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 46: Draft -> Approval -> Published -> Active -> Completed.
        // "Active" and "Completed" are derived from the dates, never stored,
        // so a campaign can't be left "active" after it ends.
        Schema::create('fundraising_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('type', 20); // institutional | crowdfunding | giving_day
            $table->string('title', 200);
            $table->string('summary', 400);
            $table->longText('story'); // Markdown, rendered safely
            $table->string('category', 30); // donation category
            $table->unsignedBigInteger('goal_paise');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('draft'); // draft | pending_approval | published | rejected | cancelled
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignUlid('cover_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->foreignId('organizer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            // Matching gift: a sponsor matches donations at `ratio` up to `cap`.
            $table->string('matching_sponsor', 160)->nullable();
            $table->decimal('matching_ratio', 4, 2)->nullable();
            $table->unsignedBigInteger('matching_cap_paise')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('campaign_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fundraising_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->timestamps();
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->foreignId('fundraising_campaign_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->index(['fundraising_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fundraising_campaign_id');
        });
        Schema::dropIfExists('campaign_updates');
        Schema::dropIfExists('fundraising_campaigns');
    }
};
