<?php

namespace App\Services;

use App\Models\EngagementActivity;
use App\Models\MentorshipRequest;
use App\Models\User;
use App\Notifications\MentorshipRequested;
use App\Notifications\MentorshipUpdated;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Mentorship lifecycle: request -> accept/decline -> complete (SRS 29). */
class MentorshipService
{
    public function __construct(
        private readonly ConnectionService $connections,
        private readonly EngagementRecorder $engagement,
    ) {}

    public function request(User $mentee, User $mentor, string $category, string $goals, ?int $score = null): MentorshipRequest
    {
        $profile = $mentor->mentorProfile;

        if ($mentee->is($mentor) || $profile === null || ! $profile->is_accepting || $this->connections->isBlockedEitherWay($mentee->id, $mentor->id)) {
            throw new InvalidArgumentException('This mentor is not taking requests.');
        }

        $type = $mentee->alumniProfile?->isVerified() ? 'alumni' : 'students';
        if (! in_array($profile->preferred_mentee, ['both', $type], true)) {
            throw new InvalidArgumentException('This mentor is mentoring '.strtolower($profile->preferred_mentee).' only.');
        }

        return DB::transaction(function () use ($mentee, $mentor, $profile, $category, $goals, $score) {
            // Serialise requests per mentor so capacity checks are reliable.
            User::whereKey($mentor->id)->lockForUpdate()->first();

            if (MentorshipRequest::where(['mentor_id' => $mentor->id, 'mentee_id' => $mentee->id])->whereIn('status', MentorshipRequest::OPEN)->exists()) {
                throw new InvalidArgumentException('You already have an open request with this mentor.');
            }
            if ($this->activeCount($mentor) >= $profile->max_mentees) {
                throw new InvalidArgumentException('This mentor has no free slots right now.');
            }

            $request = new MentorshipRequest(['category' => $category, 'goals' => $goals]);
            $request->forceFill([
                'mentor_id' => $mentor->id,
                'mentee_id' => $mentee->id,
                'status' => MentorshipRequest::PENDING,
                'match_score' => $score,
            ])->save();

            DB::afterCommit(fn () => $mentor->notify(new MentorshipRequested($request->setRelation('mentee', $mentee))));

            return $request;
        });
    }

    public function respond(User $mentor, MentorshipRequest $request, bool $accept, ?string $note): void
    {
        DB::transaction(function () use ($mentor, $request, $accept, $note) {
            User::whereKey($mentor->id)->lockForUpdate()->first();
            $request->refresh();

            if ($request->mentor_id !== $mentor->id || $request->status !== MentorshipRequest::PENDING) {
                throw new InvalidArgumentException('This request is no longer pending.');
            }
            if ($accept && $this->activeCount($mentor) >= ($mentor->mentorProfile?->max_mentees ?? 0)) {
                throw new InvalidArgumentException('You are at your mentee limit. Raise it in your mentor profile, or complete a mentorship first.');
            }

            $request->forceFill([
                'status' => $accept ? MentorshipRequest::ACCEPTED : MentorshipRequest::DECLINED,
                'mentor_note' => $note,
                'responded_at' => now(),
            ])->save();
        });

        $request->mentee->notify(new MentorshipUpdated($request));
    }

    public function complete(User $actor, MentorshipRequest $request): void
    {
        if (! in_array($actor->id, [$request->mentor_id, $request->mentee_id], true) || $request->status !== MentorshipRequest::ACCEPTED) {
            throw new InvalidArgumentException('Only an active mentorship can be completed.');
        }

        $request->forceFill(['status' => MentorshipRequest::COMPLETED, 'completed_at' => now()])->save();
        $this->engagement->record($request->mentor, 'MENTORSHIP_COMPLETED', EngagementActivity::MODE_VOLUNTEER, $request);

        $other = $actor->id === $request->mentor_id ? $request->mentee : $request->mentor;
        $other->notify(new MentorshipUpdated($request));
    }

    public function cancel(User $mentee, MentorshipRequest $request): void
    {
        if ($request->mentee_id !== $mentee->id || $request->status !== MentorshipRequest::PENDING) {
            throw new InvalidArgumentException('Only your own pending requests can be withdrawn.');
        }

        $request->forceFill(['status' => MentorshipRequest::CANCELLED])->save();
    }

    public function activeCount(User $mentor): int
    {
        return MentorshipRequest::where('mentor_id', $mentor->id)->where('status', MentorshipRequest::ACCEPTED)->count();
    }
}
