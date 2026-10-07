<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['title', 'description', 'audience', 'event_id', 'is_anonymous', 'closes_at'])]
class Survey extends Model
{
    public const QUESTION_TYPES = ['single' => 'Single choice', 'multiple' => 'Multiple choice', 'text' => 'Free text', 'rating' => 'Rating (1–5)', 'nps' => 'Recommend (0–10)'];

    protected function casts(): array
    {
        return ['audience' => 'array', 'is_anonymous' => 'boolean', 'closes_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Survey $s) => $s->slug ??= Str::slug(Str::limit($s->title, 120, '')).'-'.Str::lower(Str::random(5)));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'published' && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /** Keyed hash so duplicate detection works without storing the respondent. */
    public function respondentHash(User $user): string
    {
        return hash_hmac('sha256', "{$this->id}:{$user->id}", (string) config('app.key'));
    }
}
