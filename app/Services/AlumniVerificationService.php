<?php

namespace App\Services;

use App\Enums\VerificationStatus;
use App\Models\AlumniProfile;
use App\Models\AlumniRecord;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Notifications\VerificationDecided;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Alumni verification (SRS 20).
 *
 * A registration is verified automatically only when it matches an institute
 * record on roll number, programme and graduation year, and the name is a
 * close match. Anything less goes to a Verification Officer: a near miss is
 * attached to the request as a hint, never trusted on its own.
 */
class AlumniVerificationService
{
    /** Minimum name similarity (0-100) for an automatic match. */
    private const NAME_THRESHOLD = 80;

    public function __construct(private readonly AuditLogger $audit) {}

    public function submit(AlumniProfile $profile, ?string $note = null): VerificationRequest
    {
        return DB::transaction(function () use ($profile, $note) {
            $candidate = AlumniRecord::query()
                ->where('roll_number', $this->normaliseRoll($profile->roll_number))
                ->whereDoesntHave('profile', fn ($q) => $q->whereKeyNot($profile->getKey()))
                ->lockForUpdate()
                ->first();

            $isMatch = $candidate !== null
                && $candidate->programme_id === $profile->programme_id
                && $candidate->graduation_year === $profile->graduation_year
                && $this->nameSimilarity($candidate->name, $profile->user->name) >= self::NAME_THRESHOLD;

            $request = $profile->verificationRequests()->create([
                'status' => $isMatch ? VerificationStatus::Verified : VerificationStatus::Pending,
                'method' => $isMatch ? VerificationRequest::METHOD_AUTO : VerificationRequest::METHOD_MANUAL,
                'matched_record_id' => $candidate?->id,
                'applicant_note' => $note,
            ]);

            if ($isMatch) {
                $request->forceFill(['reviewed_at' => now(), 'decision_reason' => 'Matched institute record.'])->save();
                $this->markVerified($profile, $candidate, null);
            } else {
                $profile->forceFill(['verification_status' => VerificationStatus::Pending])->save();
            }

            $this->audit->record(
                $isMatch ? 'verification.auto_verified' : 'verification.submitted',
                'alumni',
                $profile,
                null,
                ['request_id' => $request->id, 'method' => $request->method, 'matched_record_id' => $candidate?->id],
                $profile->user_id,
            );

            return $request;
        });
    }

    public function approve(VerificationRequest $request, User $reviewer, ?string $reason = null): void
    {
        $this->decide($request, $reviewer, VerificationStatus::Verified, $reason);
    }

    public function reject(VerificationRequest $request, User $reviewer, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A rejection needs a reason the applicant can act on.');
        }

        $this->decide($request, $reviewer, VerificationStatus::Rejected, $reason);
    }

    private function decide(VerificationRequest $request, User $reviewer, VerificationStatus $decision, ?string $reason): void
    {
        DB::transaction(function () use ($request, $reviewer, $decision, $reason) {
            $request = VerificationRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($request->status !== VerificationStatus::Pending) {
                throw new InvalidArgumentException('This request has already been decided.');
            }

            $request->forceFill([
                'status' => $decision,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'decision_reason' => $reason,
            ])->save();

            $profile = $request->profile;
            $before = ['verification_status' => $profile->verification_status->value];

            if ($decision === VerificationStatus::Verified) {
                // Link the institute record only if nobody else has claimed it.
                $record = $request->matchedRecord;
                $this->markVerified($profile, $record && ! $record->profile()->exists() ? $record : null, $reviewer);
            } else {
                $profile->forceFill(['verification_status' => $decision])->save();
            }

            $this->audit->record(
                'verification.'.$decision->value,
                'alumni',
                $profile,
                $before,
                ['verification_status' => $decision->value, 'request_id' => $request->id, 'reason' => $reason],
                $reviewer,
            );

            DB::afterCommit(fn () => $profile->user->notify(new VerificationDecided($decision, $reason)));
        });
    }

    private function markVerified(AlumniProfile $profile, ?AlumniRecord $record, ?User $reviewer): void
    {
        $profile->forceFill([
            'verification_status' => VerificationStatus::Verified,
            'verified_at' => now(),
            'verified_by' => $reviewer?->id,
            'alumni_record_id' => $record?->id ?? $profile->alumni_record_id,
        ])->save();
    }

    public function normaliseRoll(string $roll): string
    {
        return Str::upper(preg_replace('/\s+/', '', $roll));
    }

    private function nameSimilarity(string $a, string $b): float
    {
        $normalise = fn (string $s) => collect(preg_split('/\s+/', Str::lower(Str::ascii($s))))
            ->map(fn ($part) => preg_replace('/[^a-z]/', '', $part))
            ->filter()
            ->sort()
            ->implode(' ');

        similar_text($normalise($a), $normalise($b), $percent);

        return $percent;
    }
}
