<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SRS 27-28 (and batch groups, module 12). Chapters are communities
        // with kind=chapter: same membership, posts and moderation model.
        Schema::create('communities', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // community | chapter
            $table->string('category', 30); // batch | programme | department | geographic | professional | interest | city | country
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('join_policy', 20)->default('open'); // open | approval | restricted
            // For restricted (batch) groups: who may join, e.g. {"programme_id":3,"graduation_year":2015}
            $table->json('eligibility')->nullable();
            $table->boolean('is_official')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'category']);
        });

        Schema::create('community_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // member | moderator | admin
            $table->string('status', 20)->default('active'); // active | pending | banned
            $table->timestamps();

            $table->unique(['community_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('community_id')->references('id')->on('communities')->nullOnDelete();
        });

        // SRS 26. Plain text only: no stored HTML means no stored XSS.
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('post'); // post | achievement | announcement
            $table->text('body');
            $table->string('link_url', 500)->nullable();
            $table->nullableMorphs('shareable'); // a shared event or job
            $table->timestamp('pinned_at')->nullable();
            $table->string('status', 20)->default('published'); // published | removed
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('removed_reason', 500)->nullable();
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['community_id', 'status', 'id']);
            $table->index(['user_id', 'id']);
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('published');
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'status', 'id']);
        });

        foreach (['post_likes', 'post_saves'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->foreignId('post_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->primary(['post_id', 'user_id']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_saves');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('posts');
        Schema::table('events', fn (Blueprint $table) => $table->dropForeign(['community_id']));
        Schema::dropIfExists('community_members');
        Schema::dropIfExists('communities');
    }
};
