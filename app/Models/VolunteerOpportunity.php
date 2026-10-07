<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category', 'title', 'description', 'location', 'is_remote', 'starts_on', 'ends_on', 'slots', 'hours_estimate', 'community_id'])]
class VolunteerOpportunity extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'mentoring' => 'Mentoring', 'events' => 'Events', 'career_guidance' => 'Career guidance', 'admissions' => 'Admissions outreach',
        'research' => 'Research', 'fundraising' => 'Fundraising', 'chapter_leadership' => 'Chapter leadership', 'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['is_remote' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date', 'slots' => 'integer', 'hours_estimate' => 'float'];
    }

    public function signups(): HasMany
    {
        return $this->hasMany(VolunteerSignup::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open')->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', today()));
    }

    public function activeSignups(): int
    {
        return $this->signups()->whereNotIn('status', ['withdrawn', 'rejected'])->count();
    }
}
