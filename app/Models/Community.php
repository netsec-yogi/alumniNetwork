<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['kind', 'category', 'name', 'description', 'join_policy', 'is_official'])]
class Community extends Model
{
    use HasFactory, SoftDeletes;

    public const KIND_COMMUNITY = 'community';

    public const KIND_CHAPTER = 'chapter';

    public const OPEN = 'open';

    public const APPROVAL = 'approval';

    /** Membership decided by eligibility rules (e.g. batch groups). */
    public const RESTRICTED = 'restricted';

    public const CATEGORIES = [
        'community' => ['batch' => 'Batch', 'programme' => 'Programme', 'department' => 'Department', 'geographic' => 'Geographic', 'professional' => 'Professional', 'interest' => 'Interest'],
        'chapter' => ['city' => 'City chapter', 'country' => 'Country chapter', 'professional' => 'Professional chapter', 'batch' => 'Batch chapter'],
    ];

    protected function casts(): array
    {
        return ['eligibility' => 'array', 'is_official' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Community $c) => $c->slug ??= Str::slug(Str::limit($c->name, 110, '')).'-'.Str::lower(Str::random(4)));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'community_members')->withPivot('role', 'status')->wherePivot('status', CommunityMember::ACTIVE);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function membershipOf(?User $user): ?CommunityMember
    {
        return $user ? $this->memberships()->where('user_id', $user->id)->first() : null;
    }

    public function isChapter(): bool
    {
        return $this->kind === self::KIND_CHAPTER;
    }

    /** Whether a user meets restricted-group rules (e.g. programme + batch). */
    public function admits(User $user): bool
    {
        if ($this->join_policy !== self::RESTRICTED) {
            return true;
        }

        $profile = $user->alumniProfile;
        if (! $profile?->isVerified()) {
            return false;
        }

        foreach ($this->eligibility ?? [] as $field => $value) {
            if ($profile->{$field} != $value) {
                return false;
            }
        }

        return true;
    }
}
