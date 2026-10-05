<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Enums\Visibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
 | Verification fields, the institute record link and the roll number are
 | deliberately absent: alumni cannot verify themselves by posting them.
 */
#[Fillable([
    'preferred_name', 'gender', 'date_of_birth', 'bio', 'specialization',
    'company', 'designation', 'industry', 'city', 'state', 'country',
    'linkedin_url', 'website_url', 'interests', 'visibility',
])]
class AlumniProfile extends Model
{
    use HasFactory, SoftDeletes;

    /** Fields whose visibility the alumnus controls, with their defaults (SRS 22). */
    public const PRIVACY_FIELDS = [
        'email' => Visibility::Alumni,
        'phone' => Visibility::Private,
        'company' => Visibility::Alumni,
        'designation' => Visibility::Alumni,
        'location' => Visibility::Alumni,
        'linkedin_url' => Visibility::Alumni,
        'bio' => Visibility::Alumni,
    ];

    public const INTERESTS = [
        'mentor' => 'Mentor students',
        'speaker' => 'Speak at events',
        'recruiter' => 'Hire / refer',
        'volunteer' => 'Volunteer',
        'research' => 'Research collaboration',
        'startup_mentor' => 'Mentor startups',
        'donor' => 'Support fundraising',
        'event_volunteer' => 'Help at events',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'interests' => 'array',
            'visibility' => 'array',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(AlumniRecord::class, 'alumni_record_id');
    }

    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    public function scopeVerified(Builder $query): void
    {
        $query->where('verification_status', VerificationStatus::Verified);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::Verified;
    }

    public function visibilityOf(string $field): Visibility
    {
        $stored = $this->visibility[$field] ?? null;

        return Visibility::tryFrom((string) $stored) ?? self::PRIVACY_FIELDS[$field];
    }

    /** Percentage of the optional profile filled in (dashboard meter, SRS 53). */
    public function completion(): int
    {
        $fields = ['company', 'designation', 'industry', 'city', 'country', 'bio', 'linkedin_url', 'specialization'];
        $filled = collect($fields)->filter(fn ($f) => filled($this->{$f}))->count() + (filled($this->interests) ? 1 : 0);

        return (int) round($filled / (count($fields) + 1) * 100);
    }

    public function displayName(): string
    {
        return $this->preferred_name ?: $this->user->name;
    }
}
