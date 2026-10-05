<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use App\Services\JobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Job moderation queue (SRS 32). */
class JobController extends Controller
{
    public function __construct(private readonly JobService $jobs) {}

    public function index(Request $request): Response
    {
        $this->authorize('moderate', JobPosting::class);
        $status = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'closed'])]])['status'] ?? 'pending';

        $jobs = JobPosting::query()
            ->where('status', $status)
            ->with(['poster:id,name,email', 'poster.roles:id,name'])
            ->withCount('referralRequests')
            ->when($status === 'pending', fn ($q) => $q->oldest(), fn ($q) => $q->latest())
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JobPosting $j) => [
                'id' => $j->id,
                'title' => $j->title,
                'organization' => $j->organization,
                'type' => JobPosting::TYPES[$j->type],
                'location' => $j->location,
                'apply' => $j->apply_url ?? $j->apply_email,
                'excerpt' => Str::limit($j->description, 280),
                'poster' => ['name' => $j->poster->name, 'email' => $j->poster->email, 'roles' => $j->poster->roles->pluck('name')],
                'submitted_at' => $j->updated_at->diffForHumans(),
                'rejection_reason' => $j->rejection_reason,
                'referrals' => $j->referral_requests_count,
            ]);

        return Inertia::render('Admin/Jobs/Index', [
            'jobs' => $jobs,
            'status' => $status,
            'counts' => JobPosting::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function approve(Request $request, JobPosting $job): RedirectResponse
    {
        $this->authorize('moderate', JobPosting::class);

        return $this->attempt(fn () => $this->jobs->approve($request->user(), $job), 'Approved; the poster has been told.');
    }

    public function reject(Request $request, JobPosting $job): RedirectResponse
    {
        $this->authorize('moderate', JobPosting::class);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']])['reason'];

        return $this->attempt(fn () => $this->jobs->reject($request->user(), $job, $reason), 'Rejected; the poster has been told why.');
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
}
