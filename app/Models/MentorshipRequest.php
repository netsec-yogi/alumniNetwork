<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category', 'goals'])]
class MentorshipRequest extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    /** Statuses that hold a mentor's slot or block a duplicate request. */
    public const OPEN = [self::PENDING, self::ACCEPTED];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime', 'completed_at' => 'datetime', 'match_score' => 'integer'];
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_id');
    }
}
