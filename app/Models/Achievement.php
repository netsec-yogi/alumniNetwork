<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category', 'title', 'description', 'achieved_on', 'link_url'])]
class Achievement extends Model
{
    use SoftDeletes;

    public const SUBMITTED = 'submitted';

    public const PUBLISHED = 'published';

    public const REJECTED = 'rejected';

    public const CATEGORIES = [
        'award' => 'Award', 'promotion' => 'Promotion', 'publication' => 'Publication', 'patent' => 'Patent',
        'startup' => 'Startup milestone', 'government' => 'Government appointment', 'international' => 'International recognition', 'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['achieved_on' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AlumniProfile::class, 'alumni_profile_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'image_file_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::PUBLISHED);
    }
}
