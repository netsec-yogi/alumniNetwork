<?php

namespace App\Models;

use App\Contracts\Moderatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Status and moderation fields are set by JobService, never from input. */
#[Fillable([
    'type', 'title', 'organization', 'location', 'work_mode', 'employment_type',
    'experience_min', 'experience_max', 'skills', 'compensation', 'description',
    'apply_url', 'apply_email', 'deadline', 'referral_available',
])]
class JobPosting extends Model implements Moderatable
{
    use HasFactory, SoftDeletes;

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CLOSED = 'closed';

    public const TYPES = ['job' => 'Job', 'internship' => 'Internship'];

    public const WORK_MODES = ['onsite' => 'On-site', 'remote' => 'Remote', 'hybrid' => 'Hybrid'];

    public const EMPLOYMENT_TYPES = ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'internship' => 'Internship'];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'deadline' => 'date',
            'referral_available' => 'boolean',
            'moderated_at' => 'datetime',
            'experience_min' => 'integer',
            'experience_max' => 'integer',
        ];
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function referralRequests(): HasMany
    {
        return $this->hasMany(JobReferralRequest::class);
    }

    /** Approved and not past its deadline. */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', self::APPROVED)
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', today()));
    }

    public function isLive(): bool
    {
        return $this->status === self::APPROVED && ($this->deadline === null || ! $this->deadline->isPast() || $this->deadline->isToday());
    }

    public function removeByModerator(User $moderator, string $reason): void
    {
        $this->forceFill(['status' => self::CLOSED, 'rejection_reason' => $reason, 'moderated_by' => $moderator->id, 'moderated_at' => now()])->save();
        $this->delete();
    }

    public function moderationSummary(): string
    {
        return "{$this->title} at {$this->organization} — ".Str::limit($this->description, 140);
    }
}
