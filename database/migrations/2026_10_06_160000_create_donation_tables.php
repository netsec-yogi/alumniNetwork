<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 45-47.
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('donor_name', 160);
            $table->string('donor_email', 255);
            $table->string('donor_phone', 20)->nullable();
            $table->text('pan')->nullable(); // encrypted
            $table->text('address')->nullable(); // encrypted
            $table->boolean('wants_80g')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->string('category', 30);
            $table->unsignedBigInteger('amount_paise');
            $table->char('currency', 3)->default('INR');
            $table->string('status', 20)->default('created'); // created | pending | paid | failed | refunded
            $table->string('gateway', 20);
            $table->string('gateway_order_id', 100)->nullable()->unique();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->string('receipt_number', 40)->nullable()->unique();
            $table->foreignUlid('receipt_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'category']);
        });

        // Gateway events already processed: replay protection for webhooks.
        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 20);
            $table->string('event_id', 120);
            $table->string('type', 60);
            $table->foreignId('donation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['gateway', 'event_id']);
        });

        // Gap-free receipt numbers per financial year.
        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->string('financial_year', 9)->primary(); // 2026-27
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_sequences');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('donations');
    }
};
