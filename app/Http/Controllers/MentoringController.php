<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\MentorProfile;
use App\Models\MentorshipRequest;
use App\Models\Programme;
use App\Models\User;
use App\Services\Ai\MentorAiRanker;
use App\Services\MentorMatchingService;
use App\Services\MentorshipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Mentoring (SRS 29-30). */
class MentoringController extends Controller
{
    public function __construct(
        private readonly MentorshipService $mentorships,
        private readonly MentorMatchingService $matching,
        private readonly MentorAiRanker $aiRanker,
    ) {}

    private function canMentor(User $user): bool
    {
        return (bool) $user->alumniProfile?->isVerified() || $user->hasRole(RoleName::Faculty->value);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember(), 403);
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['mentee', 'mentoring'])]])['tab'] ?? 'mentee';

        $row = fn (MentorshipRequest $m, User $other) => [
            'id' => $m->id,
            'other' => [
                'name' => $other->name,
                'profile_id' => $other->alumniProfile?->isVerified() ? $other->alumniProfile->id : null,
                'subtitle' => $other->alumniProfile ? "{$other->alumniProfile->programme->name} · {$other->alumniProfile->graduation_year}" : 'Student',
            ],
            'category' => config('mentoring.categories')[$m->category] ?? $m->category,
            'goals' => $m->goals,
            'status' => $m->status,
            'mentor_note' => $m->mentor_note,
            'match_score' => $m->match_score,
            'since' => ($m->responded_at ?? $m->created_at)->diffForHumans(),
            // Contact details are shared once a mentorship is accepted.
            'contact' => $m->status === MentorshipRequest::ACCEPTED ? $other->email : null,
        ];

        $with = fn (string $rel) => [$rel, "{$rel}.alumniProfile.programme:id,name"];

        $asMentee = MentorshipRequest::where('mentee_id', $user->id)->with($with('mentor'))->latest()->limit(50)->get()
            ->map(fn ($m) => $row($m, $m->mentor));
        $asMentor = MentorshipRequest::where('mentor_id', $user->id)->with($with('mentee'))
            ->orderByRaw("FIELD(status, 'pending', 'accepted', 'completed', 'declined', 'cancelled')")->latest()->limit(100)->get()
            ->map(fn ($m) => $row($m, $m->mentee));

        return Inertia::render('Mentoring/Index', [
            'tab' => $tab,
            'asMentee' => $asMentee,
            'asMentor' => $asMentor,
            'mentorProfile' => $user->mentorProfile?->only(['is_accepting', 'max_mentees']),
            'activeMentees' => $this->mentorships->activeCount($user),
            'canMentor' => $this->canMentor($user),
        ]);
    }

    public function find(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember(), 403);

        $criteria = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(config('mentoring.categories')))],
            'interests' => ['array', 'max:10'],
            'interests.*' => ['string', 'max:40'],
            'industry' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'programme_id' => ['nullable', 'integer', 'exists:programmes,id'],
            'goals' => ['nullable', 'string', 'max:1000'],
        ]);
        $searched = $request->hasAny(['category', 'interests', 'industry', 'location', 'programme_id']);

        $results = $searched ? $this->matching->match($user, $criteria)->map(fn (array $r) => [
            'user_id' => $r['mentor']->user_id,
            'name' => $r['mentor']->user->alumniProfile?->displayName() ?? $r['mentor']->user->name,
            'profile_id' => $r['mentor']->user->alumniProfile?->isVerified() ? $r['mentor']->user->alumniProfile->id : null,
            'subtitle' => $r['mentor']->user->alumniProfile
                ? "{$r['mentor']->user->alumniProfile->programme->name} · {$r['mentor']->user->alumniProfile->graduation_year}"
                : 'Faculty',
            'bio' => $r['mentor']->bio,
            'categories' => collect($r['mentor']->categories)->map(fn ($c) => config('mentoring.categories')[$c] ?? $c)->values(),
            'expertise' => $r['mentor']->expertise ?? [],
            'availability' => $r['mentor']->availability,
            'mode' => config('mentoring.modes')[$r['mentor']->preferred_mode] ?? null,
            'score' => $r['score'],
            'breakdown' => $r['breakdown'],
        ]) : collect();

        // Optional AI re-rank against the mentee's own words (SRS 96), capped per user.
        $aiEnabled = (bool) config('ai.enabled');
        if ($aiEnabled && filled($criteria['goals'] ?? null) && $results->count() > 1) {
            $results = RateLimiter::attempt('ai-mentor:'.$user->id, 20, fn () => $this->aiRanker->rerank($criteria['goals'], $results), 3600) ?: $results;
        }

        return Inertia::render('Mentoring/Find', [
            'aiEnabled' => $aiEnabled,
            'criteria' => (object) $criteria,
            'searched' => $searched,
            'results' => $results,
            'categories' => $this->options(config('mentoring.categories')),
            'programmes' => Programme::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
            'defaultProgramme' => $user->alumniProfile?->programme_id,
        ]);
    }

    public function editProfile(Request $request): Response
    {
        abort_unless($this->canMentor($request->user()), 403);

        return Inertia::render('Mentoring/Profile', [
            'profile' => $request->user()->mentorProfile,
            'categories' => $this->options(config('mentoring.categories')),
            'modes' => $this->options(config('mentoring.modes')),
            'menteeTypes' => $this->options(MentorProfile::MENTEE_TYPES),
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canMentor($user), 403);

        $data = $request->validate([
            'is_accepting' => ['boolean'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => [Rule::in(array_keys(config('mentoring.categories')))],
            'expertise' => ['array', 'max:20'],
            'expertise.*' => ['string', 'max:40'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'preferred_mentee' => ['required', Rule::in(array_keys(MentorProfile::MENTEE_TYPES))],
            'max_mentees' => ['required', 'integer', 'min:1', 'max:20'],
            'availability' => ['nullable', 'string', 'max:255'],
            'preferred_mode' => ['required', Rule::in(array_keys(config('mentoring.modes')))],
        ]);

        $user->mentorProfile()->updateOrCreate([], $data);

        // Keep the directory's "Open to: mentor" filter in step.
        if ($profile = $user->alumniProfile) {
            $interests = collect($profile->interests ?? [])->reject(fn ($i) => $i === 'mentor');
            $profile->forceFill(['interests' => ($data['is_accepting'] ?? false) ? $interests->push('mentor')->values()->all() : $interests->values()->all()])->save();
        }

        return redirect()->route('mentoring.index', ['tab' => 'mentoring'])->with('success', 'Mentor profile saved.');
    }

    public function store(Request $request, User $mentor): RedirectResponse
    {
        abort_unless($request->user()->isCommunityMember(), 403);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('mentoring.categories')))],
            'goals' => ['required', 'string', 'min:30', 'max:2000'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        return $this->attempt(fn () => $this->mentorships->request($request->user(), $mentor, $data['category'], $data['goals'], $data['score'] ?? null), 'Request sent.');
    }

    public function respond(Request $request, MentorshipRequest $mentorship): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])], 'note' => ['nullable', 'string', 'max:500']]);

        return $this->attempt(fn () => $this->mentorships->respond($request->user(), $mentorship, $data['decision'] === 'accept', $data['note'] ?? null), 'Response sent.');
    }

    public function complete(Request $request, MentorshipRequest $mentorship): RedirectResponse
    {
        return $this->attempt(fn () => $this->mentorships->complete($request->user(), $mentorship), 'Marked complete — thank you!');
    }

    public function cancel(Request $request, MentorshipRequest $mentorship): RedirectResponse
    {
        return $this->attempt(fn () => $this->mentorships->cancel($request->user(), $mentorship), 'Request withdrawn.');
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    /** @return list<array{value: string, label: string}> */
    private function options(array $map): array
    {
        return array_map(fn ($v, $l) => ['value' => $v, 'label' => $l], array_keys($map), $map);
    }
}
