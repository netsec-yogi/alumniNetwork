<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public landing page: curation flags on existing content, chapter
 * locations, a public photo gallery, and landing-page settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('batch_years');
        });

        Schema::table('stories', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
        });

        Schema::table('distinguished_alumni', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
        });

        Schema::table('communities', function (Blueprint $table) {
            $table->string('city', 100)->nullable()->after('category');
            $table->string('country', 100)->nullable()->after('city');
            // Shown publicly only because an admin typed it in for that purpose.
            $table->string('coordinator_name', 120)->nullable()->after('country');
            $table->boolean('show_on_landing')->default(false)->after('is_official');
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('stored_file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('caption', 300)->nullable();
            $table->string('category', 30);
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft | published | archived
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->date('visible_from')->nullable();
            $table->date('visible_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'is_featured', 'display_order']);
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('gallery_items');
        Schema::table('communities', fn (Blueprint $t) => $t->dropColumn(['city', 'country', 'coordinator_name', 'show_on_landing']));
        Schema::table('distinguished_alumni', fn (Blueprint $t) => $t->dropColumn(['is_featured', 'display_order']));
        Schema::table('stories', fn (Blueprint $t) => $t->dropColumn(['is_featured', 'display_order']));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('is_featured'));
    }
};
