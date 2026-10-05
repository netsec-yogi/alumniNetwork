<?php

namespace App\Models;

use App\Contracts\Moderatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['body', 'link_url', 'kind'])]
class Post extends Model implements Moderatable
{
    use HasFactory, SoftDeletes;

    public const PUBLISHED = 'published';

    public const REMOVED = 'removed';

    public const KINDS = ['post' => 'Post', 'achievement' => 'Achievement', 'announcement' => 'Announcement'];

    protected function casts(): array
    {
        return ['pinned_at' => 'datetime', 'likes_count' => 'integer', 'comments_count' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes');
    }

    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_saves');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::PUBLISHED);
    }

    public function removeByModerator(User $moderator, string $reason): void
    {
        $this->forceFill(['status' => self::REMOVED, 'removed_by' => $moderator->id, 'removed_reason' => $reason])->save();
    }

    public function moderationSummary(): string
    {
        return Str::limit($this->body, 200);
    }
}
