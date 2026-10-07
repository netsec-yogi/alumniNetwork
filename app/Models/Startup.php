<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['name', 'tagline', 'description', 'industry', 'website_url', 'location', 'founded_year', 'funding_stage', 'is_hiring'])]
class Startup extends Model
{
    use HasFactory, SoftDeletes;

    public const STAGES = [
        'bootstrapped' => 'Bootstrapped', 'pre_seed' => 'Pre-seed', 'seed' => 'Seed', 'series_a' => 'Series A',
        'series_b_plus' => 'Series B+', 'profitable' => 'Profitable', 'acquired' => 'Acquired',
    ];

    protected function casts(): array
    {
        return ['is_hiring' => 'boolean', 'is_hidden' => 'boolean', 'founded_year' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Startup $s) => $s->slug ??= Str::slug(Str::limit($s->name, 110, '')).'-'.Str::lower(Str::random(4)));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function founders(): BelongsToMany
    {
        return $this->belongsToMany(AlumniProfile::class, 'startup_founders')->withPivot('role');
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'logo_file_id');
    }

    public function isFounder(?User $user): bool
    {
        return $user?->alumniProfile !== null && $this->founders()->whereKey($user->alumniProfile->id)->exists();
    }
}
