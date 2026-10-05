<?php

namespace App\Models;

use App\Contracts\Moderatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['body'])]
class PostComment extends Model implements Moderatable
{
    use SoftDeletes;

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function removeByModerator(User $moderator, string $reason): void
    {
        $this->forceFill(['status' => Post::REMOVED, 'removed_by' => $moderator->id])->save();
        Post::whereKey($this->post_id)->where('comments_count', '>', 0)->decrement('comments_count');
    }

    public function moderationSummary(): string
    {
        return Str::limit($this->body, 200);
    }
}
