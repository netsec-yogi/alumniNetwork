<?php

namespace App\Http\Controllers;

use App\Models\AlumniProfile;
use App\Models\Connection;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\JobPosting;
use App\Models\JobReferralRequest;
use App\Models\MentorshipRequest;
use App\Services\ConnectionService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly ConnectionService $connections) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->alumniProfile?->load('programme:id,name');

        $completion = $profile?->completion();

        $latestRequest = $profile?->verificationRequests()->latest()->first(['id', 'status', 'decision_reason', 'created_at']);

        return Inertia::render('Dashboard', [
            'profile' => $profile ? [
                'programme' => $profile->programme->name,
                'graduation_year' => $profile->graduation_year,
                'verification_status' => $profile->verification_status->value,
                'rejection_reason' => $latestRequest?->status->value === 'rejected' ? $latestRequest->decision_reason : null,
                'completion' => $completion,
                'id' => $profile->id,
            ] : null,
            'canBrowseDirectory' => $user->can('viewAny', AlumniProfile::class),
            'alumniCount' => AlumniProfile::verified()->count(),
            'myEvents' => Event::published()->upcoming()
                ->whereHas('registrations', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', ['confirmed', 'waitlisted']))
                ->orderBy('starts_at')->limit(3)->get()
                ->map(fn ($e) => ['slug' => $e->slug, 'title' => $e->title, 'starts_at' => $e->starts_at->format('D j M, g:i A')]),
            // Read-only summary tiles and activity (presentation only).
            'stats' => [
                'connections' => Connection::involving($user->id)->where('status', Connection::ACCEPTED)->count(),
                'upcomingEvents' => EventRegistration::where('user_id', $user->id)->whereIn('status', ['confirmed', 'waitlisted', 'payment_pending'])->whereHas('event', fn ($q) => $q->upcoming())->count(),
                'mentorships' => MentorshipRequest::where(fn ($q) => $q->where('mentor_id', $user->id)->orWhere('mentee_id', $user->id))->where('status', MentorshipRequest::ACCEPTED)->count(),
                'liveJobs' => $user->isCommunityMember() ? JobPosting::live()->count() : null,
            ],
            'activity' => $user->notifications()->latest()->limit(6)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'read' => $n->read_at !== null,
                'at' => $n->created_at->diffForHumans(),
            ]),
            'suggestions' => $user->isCommunityMember() ? $this->connections->suggestions($user, 4) : [],
            'latestJobs' => $user->isCommunityMember() ? JobPosting::live()->latest()->limit(4)->get(['id', 'title', 'organization', 'location', 'type'])
                ->map(fn (JobPosting $j) => $j->only(['id', 'title', 'organization', 'location', 'type'])) : [],
            'pending' => [
                'mentoring' => MentorshipRequest::where('mentor_id', $user->id)->where('status', 'pending')->count(),
                'referrals' => JobReferralRequest::where('status', 'pending')->whereHas('job', fn ($q) => $q->where('posted_by', $user->id))->count(),
            ],
        ]);
    }
}
