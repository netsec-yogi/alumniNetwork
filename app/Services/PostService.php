<?php

namespace App\Services;

use App\Models\Community;
use App\Models\EngagementActivity;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Social feed: posts, comments, likes, saves (SRS 26). */
class PostService
{
    public function __construct(
        private readonly CommunityService $communities,
        private readonly ConnectionService $connections,
        private readonly EngagementRecorder $engagement,
    ) {}

    public function create(User $author, array $data, ?Community $community = null, ?Model $shareable = null): Post
    {
        if ($community && ! $this->communities->isActiveMember($author, $community) && ! $this->communities->isGlobalModerator($author, $community)) {
            throw new InvalidArgumentException('Join the group to post in it.');
        }

        $kind = $data['kind'] ?? 'post';
        if ($kind === 'announcement' && ! ($community ? $this->communities->canModerate($author, $community) : $author->can('communities.moderate'))) {
            $kind = 'post';
        }

        $post = new Post(['body' => $data['body'], 'link_url' => $data['link_url'] ?? null, 'kind' => $kind]);
        $post->forceFill([
            'user_id' => $author->id,
            'community_id' => $community?->id,
            'shareable_type' => $shareable?->getMorphClass(),
            'shareable_id' => $shareable?->getKey(),
        ])->save();

        $this->engagement->record($author, 'COMMUNITY_POST', EngagementActivity::MODE_COMMUNICATION, $post);

        return $post;
    }

    /** Posts this user may read, minus anything from blocked users. */
    public function visibleTo(User $user): Builder
    {
        $readable = $this->communities->readableIds($user);
        $blocked = $this->connections->blockedIds($user->id);

        return Post::query()
            ->published()
            ->where(fn ($q) => $q->whereNull('community_id')->orWhereIn('community_id', $readable))
            ->when($blocked->isNotEmpty(), fn ($q) => $q->whereNotIn('user_id', $blocked));
    }

    public function toggleLike(User $user, Post $post): bool
    {
        return $this->toggle('post_likes', 'likes_count', $user, $post);
    }

    public function toggleSave(User $user, Post $post): bool
    {
        $key = ['post_id' => $post->id, 'user_id' => $user->id];

        if (DB::table('post_saves')->where($key)->delete() > 0) {
            return false;
        }
        DB::table('post_saves')->insertOrIgnore($key);

        return true;
    }

    public function comment(User $user, Post $post, string $body): PostComment
    {
        $comment = $post->comments()->make(['body' => $body]);
        $comment->forceFill(['user_id' => $user->id, 'status' => Post::PUBLISHED])->save();
        $post->increment('comments_count');

        return $comment;
    }

    public function deleteComment(PostComment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $comment->delete();
            Post::whereKey($comment->post_id)->where('comments_count', '>', 0)->decrement('comments_count');
        });
    }

    /** Insert-or-delete with a denormalised counter; returns the new state. */
    private function toggle(string $table, string $counter, User $user, Post $post): bool
    {
        return DB::transaction(function () use ($table, $counter, $user, $post) {
            $key = ['post_id' => $post->id, 'user_id' => $user->id];

            if (DB::table($table)->where($key)->delete() > 0) {
                Post::whereKey($post->id)->where($counter, '>', 0)->decrement($counter);

                return false;
            }

            DB::table($table)->insertOrIgnore($key);
            Post::whereKey($post->id)->increment($counter);

            return true;
        });
    }
}
