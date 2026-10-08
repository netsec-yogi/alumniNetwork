<?php

namespace App\Http\Controllers;

use App\Models\AlumniProfile;
use App\Models\Community;
use App\Models\Connection;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\JobPosting;
use App\Models\JobReferralRequest;
use App\Models\MentorshipRequest;
use App\Models\Post;
use App\Models\Report;
use App\Services\ConnectionService;
use App\Services\PostService;
use App\Support\PostPresenter;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly ConnectionService $connections, private readonly PostService $posts) {}

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
            // Home feed (presentation only): the same visibility rules as the Feed page.
            'reportReasons' => Report::REASONS,
            'feed' => fn () => $user->isCommunityMember() ? $this->homeFeed($user) : [],
            'announcements' => fn () => $user->isCommunityMember() ? $this->posts->visibleTo($user)->where('kind', 'announcement')->with('author:id,name')->latest()->limit(2)->get()
                ->map(fn (Post $p) => ['id' => $p->id, 'body' => Str::limit($p->body, 180), 'author' => $p->author->name, 'at' => $p->created_at->diffForHumans()]) : [],
            'trending' => fn () => $user->isCommunityMember() ? Community::query()
                ->withCount(['posts as recent_posts' => fn ($q) => $q->where('created_at', '>=', now()->subWeek())->where('status', Post::PUBLISHED)])
                ->withCount(['memberships as member_count' => fn ($q) => $q->where('status', 'active')])
                ->having('recent_posts', '>', 0)->orderByDesc('recent_posts')->limit(5)->get(['id', 'name', 'slug', 'category'])
                ->map(fn (Community $c) => ['name' => $c->name, 'slug' => $c->slug, 'posts' => $c->recent_posts, 'members' => $c->member_count]) : [],
            'upcoming' => fn () => Event::published()->upcoming()
                ->when(! $user->isCommunityMember(), fn ($q) => $q->where('audience', Event::AUDIENCE_PUBLIC))
                ->orderBy('starts_at')->limit(3)->get(['id', 'slug', 'title', 'starts_at', 'venue', 'is_online'])
                ->map(fn (Event $e) => ['slug' => $e->slug, 'title' => $e->title, 'day' => $e->starts_at->format('j'), 'month' => $e->starts_at->format('M'), 'when' => $e->starts_at->format('D, g:i A'), 'where' => $e->is_online ? 'Online' : $e->venue]),
            'pending' => [
                'mentoring' => MentorshipRequest::where('mentor_id', $user->id)->where('status', 'pending')->count(),
                'referrals' => JobReferralRequest::where('status', 'pending')->whereHas('job', fn ($q) => $q->where('posted_by', $user->id))->count(),
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function homeFeed($user): array
    {
        $posts = $this->posts->visibleTo($user)->with(PostPresenter::WITH)->orderByDesc('id')->limit(10)->get();
        $presenter = new PostPresenter($user, $posts);

        return $posts->map(fn (Post $p) => $presenter->present($p))->all();
    }
}
