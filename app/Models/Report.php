<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** An abuse report on a profile, post, comment or job. */
#[Fillable(['reason', 'details'])]
class Report extends Model
{
    public const REASONS = [
        'spam' => 'Spam or scam',
        'harassment' => 'Harassment or hate',
        'impersonation' => 'Fake profile or impersonation',
        'inappropriate' => 'Inappropriate content',
        'misleading' => 'False or misleading',
        'other' => 'Something else',
    ];

    public const OPEN = 'open';

    public const ACTIONED = 'actioned';

    public const DISMISSED = 'dismissed';

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reportable(): MorphTo
    {
        // Every reportable model soft-deletes, so removed content stays reviewable.
        return $this->morphTo()->withTrashed();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
