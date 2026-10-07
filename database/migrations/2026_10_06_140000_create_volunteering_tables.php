<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 44.
        Schema::create('volunteer_opportunities', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30);
            $table->string('title', 200);
            $table->text('description');
            $table->string('location', 160)->nullable();
            $table->boolean('is_remote')->default(false);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('slots')->nullable();
            $table->decimal('hours_estimate', 5, 1)->nullable();
            $table->string('status', 20)->default('open'); // open | closed
            $table->foreignId('community_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_on']);
        });

        Schema::create('volunteer_signups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volunteer_opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('signed_up'); // signed_up | withdrawn | hours_submitted | completed | rejected
            $table->text('motivation')->nullable();
            $table->decimal('hours', 5, 1)->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['volunteer_opportunity_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_signups');
        Schema::dropIfExists('volunteer_opportunities');
    }
};
