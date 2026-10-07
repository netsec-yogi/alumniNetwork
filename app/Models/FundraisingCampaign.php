<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['type', 'title', 'summary', 'story', 'category', 'goal_paise', 'starts_at', 'ends_at', 'community_id', 'matching_sponsor', 'matching_ratio', 'matching_cap_paise'])]
class FundraisingCampaign extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['institutional' => 'Institute campaign', 'crowdfunding' => 'Alumni crowdfunding', 'giving_day' => 'Giving Day'];

    protected function casts(): array
    {
        return [
            'goal_paise' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'matching_ratio' => 'float',
            'matching_cap_paise' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (FundraisingCampaign $c) => $c->slug ??= Str::slug(Str::limit($c->title, 120, '')).'-'.Str::lower(Str::random(4)));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'cover_file_id');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(CampaignUpdate::class)->latest();
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function scopeActive(Builder $query): void
    {
        $query->published()->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /** SRS 46 stage, derived from status and dates. */
    public function stage(): string
    {
        if ($this->status !== 'published') {
            return $this->status;
        }

        return match (true) {
            $this->starts_at->isFuture() => 'upcoming',
            $this->ends_at->isPast() => 'completed',
            default => 'active',
        };
    }

    public function isAcceptingDonations(): bool
    {
        return $this->stage() === 'active';
    }

    public function raisedPaise(): int
    {
        return (int) $this->donations()->where('status', Donation::PAID)->sum('amount_paise');
    }

    /** Sponsor's matched amount: computed from paid gifts, never stored as money. */
    public function matchedPaise(int $raised): int
    {
        if (! $this->matching_ratio) {
            return 0;
        }
        $matched = (int) round($raised * $this->matching_ratio);

        return $this->matching_cap_paise ? min($matched, $this->matching_cap_paise) : $matched;
    }

    public function storyHtml(): string
    {
        return Str::markdown($this->story, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
