<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['type', 'title', 'excerpt', 'body', 'video_url', 'alumni_profile_id', 'batch_year', 'programme_id', 'industry', 'location', 'is_featured', 'display_order'])]
class Story extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['article' => 'Article', 'interview' => 'Interview', 'video' => 'Video', 'photo_story' => 'Photo story', 'news' => 'News', 'announcement' => 'Announcement'];

    /** News-style types (landing page "News & announcements"); the rest are alumni stories. */
    public const NEWS_TYPES = ['news', 'announcement'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'batch_year' => 'integer', 'is_featured' => 'boolean', 'display_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Story $s) => $s->slug ??= Str::slug(Str::limit($s->title, 120, '')).'-'.Str::lower(Str::random(5)));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'cover_file_id');
    }

    /** Gallery, in display order (featured first). */
    public function images(): HasMany
    {
        return $this->hasMany(StoryImage::class)->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id');
    }

    public function featuredImage(): HasOne
    {
        return $this->hasOne(StoryImage::class)->where('is_featured', true);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AlumniProfile::class, 'alumni_profile_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')->where('published_at', '<=', now());
    }

    /**
     * Markdown to HTML for display. Raw HTML in the source is stripped and
     * javascript:/data: links are refused (SRS 60), so the output is safe
     * to render even though it bypasses Vue's escaping.
     */
    public function bodyHtml(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }
}
