<?php

namespace App\Services;

use App\Models\MentorProfile;
use App\Models\MentorshipRequest;
use App\Models\Programme;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Rule-based mentor matching (SRS 30). Each component scores 0..1 and is
 * weighted by config/mentoring.php; the result is out of 100 with a
 * breakdown, so members can see why someone was suggested.
 *
 * Location only counts when the mentor has made it visible to this mentee:
 * otherwise a high score would quietly reveal where they live.
 */
class MentorMatchingService
{
    public function __construct(
        private readonly ProfileVisibility $visibility,
        private readonly ConnectionService $connections,
    ) {}

    /**
     * @param  array{category?: string|null, interests?: list<string>, industry?: string|null, location?: string|null, programme_id?: int|null}  $criteria
     * @return Collection<int, array<string, mixed>>
     */
    public function match(User $mentee, array $criteria): Collection
    {
        $menteeType = $mentee->alumniProfile?->isVerified() ? 'alumni' : 'students';
        $programmeId = $criteria['programme_id'] ?? $mentee->alumniProfile?->programme_id;
        $programme = $programmeId ? Programme::find($programmeId) : null;
        $interests = collect($criteria['interests'] ?? [])->map(fn ($i) => Str::lower(trim($i)))->filter()->unique();
        $blocked = $this->connections->blockedIds($mentee->id);

        $active = MentorshipRequest::query()
            ->where('status', MentorshipRequest::ACCEPTED)
            ->selectRaw('mentor_id, count(*) as n')->groupBy('mentor_id')->pluck('n', 'mentor_id');

        $weights = config('mentoring.weights');
        $total = max(1, array_sum($weights));

        return MentorProfile::query()
            ->where('is_accepting', true)
            ->where('user_id', '!=', $mentee->id)
            ->whereNotIn('user_id', $blocked)
            ->whereIn('preferred_mentee', ['both', $menteeType])
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->with(['user:id,name,status', 'user.alumniProfile.programme.department'])
            ->get()
            ->filter(fn (MentorProfile $m) => ($active[$m->user_id] ?? 0) < $m->max_mentees)
            ->map(function (MentorProfile $m) use ($mentee, $criteria, $programme, $interests, $active, $weights, $total) {
                $p = $m->user->alumniProfile;

                $components = [
                    'programme' => match (true) {
                        $programme === null || $p === null => 0.0,
                        $p->programme_id === $programme->id => 1.0,
                        $p->programme->department_id !== null && $p->programme->department_id === $programme->department_id => 0.5,
                        default => 0.0,
                    },
                    'skills' => $interests->isEmpty() ? 0.0 : min(1.0, $interests->intersect(
                        collect($m->expertise ?? [])->map(fn ($e) => Str::lower($e))
                    )->count() / $interests->count()),
                    'career_interest' => ! empty($criteria['category']) && in_array($criteria['category'], $m->categories ?? [], true) ? 1.0 : 0.0,
                    'industry' => ! empty($criteria['industry']) && $p?->industry && Str::contains(Str::lower($p->industry), Str::lower($criteria['industry'])) ? 1.0 : 0.0,
                    'experience' => $p ? min(1.0, max(0, now()->year - $p->graduation_year) / config('mentoring.experience_full_years')) : 0.5,
                    'mentor_preference' => 1.0, // mismatches were filtered out above
                    'location' => $this->locationScore($p, $mentee, $criteria['location'] ?? null),
                    'availability' => 1 - (($active[$m->user_id] ?? 0) / max(1, $m->max_mentees)),
                ];

                $score = (int) round(collect($components)->map(fn ($v, $k) => $v * ($weights[$k] ?? 0))->sum() / $total * 100);

                return [
                    'mentor' => $m,
                    'score' => $score,
                    'breakdown' => collect($components)->map(fn ($v, $k) => (int) round($v * ($weights[$k] ?? 0)))->filter()->all(),
                ];
            })
            ->sortByDesc('score')
            ->take(config('mentoring.results'))
            ->values();
    }

    private function locationScore($profile, User $mentee, ?string $wanted): float
    {
        if (! $profile || ! $wanted || ! $this->visibility->canSee($profile, 'location', $mentee)) {
            return 0.0;
        }

        $wanted = Str::lower($wanted);

        return match (true) {
            $profile->city && Str::contains(Str::lower($profile->city), $wanted) => 1.0,
            $profile->country && Str::contains(Str::lower($profile->country), $wanted) => 0.5,
            default => 0.0,
        };
    }
}
