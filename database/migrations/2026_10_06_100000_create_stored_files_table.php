<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 71-73. Every upload, whatever it's for, is recorded here and
        // served only through FileController after an authorisation check.
        Schema::create('stored_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 40); // profile_photo | story_image | achievement_image | message_attachment | ...
            $table->string('kind', 20); // image | document
            $table->string('path', 255);
            $table->string('thumb_path', 255)->nullable();
            $table->string('original_name', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->char('sha256', 64);
            $table->string('visibility', 20)->default('members'); // public | members | private
            $table->string('scan_status', 20); // clean | skipped
            $table->nullableMorphs('attachable');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'purpose']);
        });

        Schema::table('alumni_profiles', function (Blueprint $table) {
            $table->dropColumn('photo_path');
            $table->foreignUlid('photo_file_id')->nullable()->after('date_of_birth')->constrained('stored_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alumni_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photo_file_id');
            $table->string('photo_path')->nullable();
        });
        Schema::dropIfExists('stored_files');
    }
};
