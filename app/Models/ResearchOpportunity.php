<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['type', 'title', 'description', 'areas', 'organization', 'closes_on'])]
class ResearchOpportunity extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'project' => 'Research project', 'collaboration' => 'Industry collaboration', 'guest_lecture' => 'Guest lecture',
        'consultancy' => 'Consultancy', 'internship' => 'Research internship',
    ];

    protected function casts(): array
    {
        return ['areas' => 'array', 'closes_on' => 'date'];
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function interests(): HasMany
    {
        return $this->hasMany(ResearchInterest::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open')->where(fn ($q) => $q->whereNull('closes_on')->orWhere('closes_on', '>=', today()));
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && ($this->closes_on === null || ! $this->closes_on->lt(today()));
    }
}
