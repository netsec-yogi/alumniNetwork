<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\EngagementActivity;
use App\Models\JobPosting;
use App\Models\JobReferralRequest;
use App\Models\User;
use App\Notifications\JobModerated;
use App\Notifications\JobPendingModeration;
use App\Notifications\ReferralRequested;
use App\Notifications\ReferralResponded;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Job and internship postings and referrals (SRS 31-33).
 *
 * Posts by career staff go live immediately; everyone else's wait for a
 * moderator, and an edit to a live post sends it back for review, so an
 * approved listing cannot be swapped for something else afterwards.
 */
class JobService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EngagementRecorder $engagement,
    ) {}

    public function create(User $poster, array $data): JobPosting
    {
        $job = new JobPosting($data);
        $trusted = $poster->can(Permission::JobsModerate->value);

        $job->forceFill([
            'posted_by' => $poster->id,
            'status' => $trusted ? JobPosting::APPROVED : JobPosting::PENDING,
            'moderated_by' => $trusted ? $poster->id : null,
            'moderated_at' => $trusted ? now() : null,
        ])->save();

        $trusted ? $this->recordPosted($job) : $this->notifyModerators($job);

        return $job;
    }

    public function update(User $actor, JobPosting $job, array $data): void
    {
        $job->fill($data);

        if (! $actor->can(Permission::JobsModerate->value) && $job->isDirty() && $job->status === JobPosting::APPROVED) {
            $job->forceFill(['status' => JobPosting::PENDING, 'moderated_by' => null, 'moderated_at' => null]);
            $job->save();
            $this->notifyModerators($job);

            return;
        }

        $job->save();
    }

    public function approve(User $moderator, JobPosting $job): void
    {
        $this->moderate($moderator, $job, JobPosting::APPROVED, null);
        $this->recordPosted($job);
    }

    public function reject(User $moderator, JobPosting $job, string $reason): void
    {
        $this->moderate($moderator, $job, JobPosting::REJECTED, $reason);
    }

    public function close(User $actor, JobPosting $job): void
    {
        $job->forceFill(['status' => JobPosting::CLOSED])->save();
        $this->audit->record('job.closed', 'career', $job, null, null, $actor);
    }

    public function requestReferral(User $requester, JobPosting $job, string $message, ?string $profileUrl): JobReferralRequest
    {
        if (! $job->isLive() || ! $job->referral_available) {
            throw new InvalidArgumentException('This posting is not taking referral requests.');
        }
        if ($job->referralRequests()->where('requester_id', $requester->id)->exists()) {
            throw new InvalidArgumentException('You have already asked for a referral for this role.');
        }

        $request = $job->referralRequests()->make(['message' => $message, 'profile_url' => $profileUrl]);
        $request->forceFill(['requester_id' => $requester->id, 'status' => JobReferralRequest::PENDING])->save();

        $job->poster->notify(new ReferralRequested($request->setRelation('requester', $requester)->setRelation('job', $job)));

        return $request;
    }

    public function respondToReferral(User $poster, JobReferralRequest $request, bool $accept, ?string $note): void
    {
        if ($request->job->posted_by !== $poster->id || $request->status !== JobReferralRequest::PENDING) {
            throw new InvalidArgumentException('This request is no longer pending.');
        }

        $request->forceFill([
            'status' => $accept ? JobReferralRequest::ACCEPTED : JobReferralRequest::DECLINED,
            'response_note' => $note,
            'responded_at' => now(),
        ])->save();

        if ($accept) {
            $this->engagement->record($poster, 'JOB_REFERRAL', EngagementActivity::MODE_VOLUNTEER, $request);
        }

        $request->requester->notify(new ReferralResponded($request));
    }

    private function moderate(User $moderator, JobPosting $job, string $status, ?string $reason): void
    {
        if ($job->status !== JobPosting::PENDING) {
            throw new InvalidArgumentException('Only pending postings can be moderated.');
        }

        $job->forceFill([
            'status' => $status,
            'rejection_reason' => $reason,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
        ])->save();

        $this->audit->record("job.{$status}", 'career', $job, null, ['reason' => $reason], $moderator);
        $job->poster->notify(new JobModerated($job));
    }

    private function recordPosted(JobPosting $job): void
    {
        $this->engagement->record($job->poster, 'JOB_POSTED', EngagementActivity::MODE_VOLUNTEER, $job);
    }

    private function notifyModerators(JobPosting $job): void
    {
        $moderators = User::permission(Permission::JobsModerate->value)->where('status', 'active')->get();
        Notification::send($moderators, new JobPendingModeration($job));
    }
}
