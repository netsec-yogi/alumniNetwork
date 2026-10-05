<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobPostingRequest;
use App\Models\JobPosting;
use App\Models\JobReferralRequest;
use App\Models\Report;
use App\Services\JobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Jobs, internships and referrals (SRS 31-33). */
class JobController extends Controller
{
    public function __construct(private readonly JobService $jobs) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', JobPosting::class);
        $user = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_keys(JobPosting::TYPES))],
            'work_mode' => ['nullable', Rule::in(array_keys(JobPosting::WORK_MODES))],
            'location' => ['nullable', 'string', 'max:100'],
            'referral' => ['nullable', 'boolean'],
            'mine' => ['nullable', 'boolean'],
        ]);
        $like = fn (string $v) => '%'.addcslashes($v, '%_\\').'%';

        $jobs = JobPosting::query()
            ->when(! empty($filters['mine']), fn ($q) => $q->where('posted_by', $user->id), fn ($q) => $q->live())
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q
                ->where('title', 'like', $like($t))->orWhere('organization', 'like', $like($t))->orWhereJsonContains('skills', $t)))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['work_mode'] ?? null, fn ($q, $v) => $q->where('work_mode', $v))
            ->when($filters['location'] ?? null, fn ($q, $v) => $q->where('location', 'like', $like($v)))
            ->when(! empty($filters['referral']), fn ($q) => $q->where('referral_available', true))
            ->with('poster:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (JobPosting $j) => $this->card($j));

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'filters' => (object) $filters,
            'options' => $this->options(),
            'canPost' => $user->can('create', JobPosting::class),
        ]);
    }

    public function show(Request $request, JobPosting $job): Response
    {
        $this->authorize('view', $job);
        $user = $request->user();
        $job->load('poster:id,name');

        return Inertia::render('Jobs/Show', [
            'job' => [
                ...$this->card($job),
                'description' => $job->description,
                'apply_url' => $job->apply_url,
                'apply_email' => $job->apply_email,
                'rejection_reason' => $job->posted_by === $user->id ? $job->rejection_reason : null,
                'poster_profile_id' => $job->poster->alumniProfile()->value('id'),
            ],
            'myReferral' => $job->referralRequests()->where('requester_id', $user->id)->first(['status', 'response_note', 'created_at']),
            'can' => [
                'update' => $user->can('update', $job),
                'close' => $user->can('close', $job),
                'requestReferral' => $user->can('requestReferral', $job),
            ],
            'reportReasons' => Report::REASONS,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', JobPosting::class);

        return Inertia::render('Jobs/Form', ['job' => null, 'options' => $this->options()]);
    }

    public function store(JobPostingRequest $request): RedirectResponse
    {
        $job = $this->jobs->create($request->user(), $request->validated());

        return redirect()->route('jobs.show', $job)->with('success', $job->status === JobPosting::APPROVED
            ? 'Posted.'
            : 'Submitted. A career administrator will review it shortly.');
    }

    public function edit(JobPosting $job): Response
    {
        $this->authorize('update', $job);

        return Inertia::render('Jobs/Form', [
            'job' => [...$job->only(array_merge($job->getFillable(), ['id', 'status'])), 'deadline' => $job->deadline?->toDateString()],
            'options' => $this->options(),
        ]);
    }

    public function update(JobPostingRequest $request, JobPosting $job): RedirectResponse
    {
        $wasApproved = $job->status === JobPosting::APPROVED;
        $this->jobs->update($request->user(), $job, $request->validated());

        return redirect()->route('jobs.show', $job)->with('success', $wasApproved && $job->status === JobPosting::PENDING
            ? 'Saved. Edits to a live posting are reviewed again before they appear.'
            : 'Saved.');
    }

    public function close(Request $request, JobPosting $job): RedirectResponse
    {
        $this->authorize('close', $job);
        $this->jobs->close($request->user(), $job);

        return back()->with('success', 'Posting closed.');
    }

    public function requestReferral(Request $request, JobPosting $job): RedirectResponse
    {
        $this->authorize('requestReferral', $job);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:30', 'max:2000'],
            'profile_url' => ['nullable', 'url:https', 'max:255'],
        ]);

        try {
            $this->jobs->requestReferral($request->user(), $job, $data['message'], $data['profile_url'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Request sent. The poster decides whether to refer you.');
    }

    public function referrals(Request $request): Response
    {
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['received', 'sent'])]])['tab'] ?? 'received';
        $user = $request->user();

        $requests = JobReferralRequest::query()
            ->when($tab === 'received', fn ($q) => $q->whereHas('job', fn ($j) => $j->where('posted_by', $user->id)))
            ->when($tab === 'sent', fn ($q) => $q->where('requester_id', $user->id))
            ->with(['job:id,title,organization,posted_by', 'job.poster:id,name', 'requester:id,name', 'requester.alumniProfile:id,user_id,programme_id,graduation_year,verification_status', 'requester.alumniProfile.programme:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JobReferralRequest $r) => [
                'id' => $r->id,
                'job' => ['id' => $r->job->id, 'title' => $r->job->title, 'organization' => $r->job->organization, 'poster' => $r->job->poster->name],
                'requester' => [
                    'name' => $r->requester->name,
                    'subtitle' => $r->requester->alumniProfile ? "{$r->requester->alumniProfile->programme->name} · {$r->requester->alumniProfile->graduation_year}" : 'Student',
                    'profile_id' => $r->requester->alumniProfile?->isVerified() ? $r->requester->alumniProfile->id : null,
                ],
                'message' => $r->message,
                'profile_url' => $r->profile_url,
                'status' => $r->status,
                'response_note' => $r->response_note,
                'at' => $r->created_at->diffForHumans(),
            ]);

        return Inertia::render('Jobs/Referrals', ['tab' => $tab, 'requests' => $requests]);
    }

    public function respondReferral(Request $request, JobReferralRequest $referral): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'decline'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->jobs->respondToReferral($request->user(), $referral, $data['decision'] === 'accept', $data['note'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['decision'] === 'accept' ? 'Thanks for referring! They’ve been told.' : 'Declined; they’ve been told.');
    }

    /** @return array<string, mixed> */
    private function card(JobPosting $j): array
    {
        return [
            'id' => $j->id,
            'type' => $j->type,
            'title' => $j->title,
            'organization' => $j->organization,
            'location' => $j->location,
            'work_mode' => JobPosting::WORK_MODES[$j->work_mode] ?? $j->work_mode,
            'employment_type' => JobPosting::EMPLOYMENT_TYPES[$j->employment_type] ?? $j->employment_type,
            'experience' => match (true) {
                $j->experience_min === null && $j->experience_max === null => null,
                $j->experience_max === null => "{$j->experience_min}+ yrs",
                default => ($j->experience_min ?? 0)."–{$j->experience_max} yrs",
            },
            'skills' => $j->skills ?? [],
            'compensation' => $j->compensation,
            'deadline' => $j->deadline?->format('j M Y'),
            'referral_available' => $j->referral_available,
            'status' => $j->status,
            'is_live' => $j->isLive(),
            'posted_by' => $j->poster?->name,
            'posted_at' => $j->created_at->diffForHumans(),
        ];
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    private function options(): array
    {
        $o = fn (array $m) => array_map(fn ($v, $l) => ['value' => $v, 'label' => $l], array_keys($m), $m);

        return ['types' => $o(JobPosting::TYPES), 'workModes' => $o(JobPosting::WORK_MODES), 'employmentTypes' => $o(JobPosting::EMPLOYMENT_TYPES)];
    }
}
