<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Image galleries for events and news. Events reuse event_photos (which
 * already holds attendee photos): official, admin-managed images are
 * flagged, ordered, and one may be featured. News gets story_images.
 * File facts (size, MIME, width, height, paths) live on stored_files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            $table->boolean('is_official')->default(false)->after('caption');
            $table->boolean('is_featured')->default(false)->after('is_official');
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
            $table->index(['event_id', 'is_official', 'sort_order']);
        });

        Schema::create('story_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('file_id')->constrained('stored_files')->cascadeOnDelete();
            $table->string('caption', 200)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['story_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_images');
        Schema::table('event_photos', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'is_official', 'sort_order']);
            $table->dropColumn(['is_official', 'is_featured', 'sort_order']);
        });
    }
};
