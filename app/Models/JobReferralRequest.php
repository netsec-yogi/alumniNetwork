<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['message', 'profile_url'])]
class JobReferralRequest extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_posting_id')->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
