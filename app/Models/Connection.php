<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A connection or pending request between two users (SRS 24). */
class Connection extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /** Rows linking $a and $b in either direction. */
    public function scopeBetween(Builder $query, int $a, int $b): void
    {
        $query->where(fn ($q) => $q->where(['requester_id' => $a, 'addressee_id' => $b])
            ->orWhere(fn ($q) => $q->where(['requester_id' => $b, 'addressee_id' => $a])));
    }

    public function scopeInvolving(Builder $query, int $userId): void
    {
        $query->where(fn ($q) => $q->where('requester_id', $userId)->orWhere('addressee_id', $userId));
    }

    public function otherParty(int $userId): int
    {
        return $this->requester_id === $userId ? $this->addressee_id : $this->requester_id;
    }
}
