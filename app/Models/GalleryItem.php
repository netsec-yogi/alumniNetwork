<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo curated for the public landing-page gallery. Status is set by
 * the admin controller (never mass-assigned); only published items inside
 * their date window, whose file is public, are ever shown to visitors.
 */
#[Fillable(['title', 'caption', 'category', 'event_id', 'is_featured', 'display_order', 'visible_from', 'visible_until'])]
class GalleryItem extends Model
{
    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const ARCHIVED = 'archived';

    public const CATEGORIES = [
        'alumni_meet' => 'Alumni meets',
        'reunion' => 'Reunions',
        'campus' => 'Campus events',
        'convocation' => 'Convocation',
        'memories' => 'Institute memories',
        'chapter' => 'Chapter events',
    ];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'display_order' => 'integer', 'visible_from' => 'date', 'visible_until' => 'date'];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'stored_file_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** What a signed-out visitor may see. */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('status', self::PUBLISHED)
            ->where(fn ($q) => $q->whereNull('visible_from')->orWhere('visible_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('visible_until')->orWhere('visible_until', '>=', today()))
            ->whereHas('file', fn ($q) => $q->where('visibility', StoredFile::PUBLIC));
    }
}
