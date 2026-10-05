<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Role and status are set by CommunityService only. */
#[Fillable(['community_id', 'user_id'])]
class CommunityMember extends Model
{
    public const ACTIVE = 'active';

    public const PENDING = 'pending';

    public const BANNED = 'banned';

    public const MEMBER = 'member';

    public const MODERATOR = 'moderator';

    public const ADMIN = 'admin';

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function canModerate(): bool
    {
        return $this->isActive() && in_array($this->role, [self::MODERATOR, self::ADMIN], true);
    }
}
