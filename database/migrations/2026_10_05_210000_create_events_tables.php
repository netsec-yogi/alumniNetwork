<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 35-38.
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 140)->unique();
            $table->string('title', 160);
            $table->string('type', 30);
            $table->string('summary', 300)->nullable();
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('venue', 255)->nullable();
            $table->boolean('is_online')->default(false);
            // Shown only to confirmed registrants.
            $table->string('online_url', 255)->nullable();
            $table->unsignedInteger('capacity')->nullable(); // null = unlimited
            $table->unsignedTinyInteger('max_guests')->default(0);
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->string('audience', 20)->default('members'); // public | members
            $table->string('status', 20)->default('draft'); // draft | published | cancelled
            $table->string('cancellation_reason', 500)->nullable();
            // Chapter/community hosting the event; constrained once that table exists.
            $table->unsignedBigInteger('community_id')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20); // confirmed | waitlisted | cancelled
            $table->unsignedTinyInteger('guests')->default(0);
            // Bearer secret printed in the QR code; never listed back to staff.
            $table->char('ticket_code', 40)->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
    }
};
