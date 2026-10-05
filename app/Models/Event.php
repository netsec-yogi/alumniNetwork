<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 | Status, slug and creator are set by EventController, never from input.
 */
#[Fillable([
    'title', 'type', 'summary', 'description', 'starts_at', 'ends_at', 'venue',
    'is_online', 'online_url', 'capacity', 'max_guests', 'registration_opens_at',
    'registration_closes_at', 'audience', 'community_id',
])]
class Event extends Model
{
    use HasFactory, SoftDeletes;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const CANCELLED = 'cancelled';

    public const AUDIENCE_PUBLIC = 'public';

    public const AUDIENCE_MEMBERS = 'members';

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'is_online' => 'boolean',
            'capacity' => 'integer',
            'max_guests' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            $event->slug ??= Str::slug(Str::limit($event->title, 100, '')).'-'.Str::lower(Str::random(6));
        });
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::PUBLISHED);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('ends_at', '>=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->isPast();
    }

    public function registrationOpen(): bool
    {
        return $this->isPublished()
            && ! $this->hasEnded()
            && ($this->registration_opens_at === null || $this->registration_opens_at->isPast())
            && ($this->registration_closes_at === null || $this->registration_closes_at->isFuture());
    }

    /** Seats taken by confirmed registrations, counting each guest. */
    public function confirmedSeats(): int
    {
        return (int) $this->registrations()
            ->where('status', EventRegistration::CONFIRMED)
            ->sum(DB::raw('1 + guests'));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
