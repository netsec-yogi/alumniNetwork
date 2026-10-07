<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 36 (paid events) and 38 (reunions: batch targeting, photos).
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('fee_paise')->default(0)->after('max_guests'); // per person, guests included
            $table->json('batch_years')->nullable()->after('audience'); // reunion target batches
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('reference', 40)->nullable()->unique()->after('ticket_code');
            $table->unsignedBigInteger('amount_paise')->default(0);
            $table->string('payment_status', 20)->default('not_required'); // not_required | pending | paid | refunded
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_order_id', 100)->nullable()->unique();
            $table->string('gateway_payment_id', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            // Seat held while payment is pending; released by the scheduler.
            $table->timestamp('hold_expires_at')->nullable()->index();
        });

        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('caption', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_photos');
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn(['reference', 'amount_paise', 'payment_status', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'paid_at', 'hold_expires_at']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['fee_paise', 'batch_years']);
        });
    }
};
