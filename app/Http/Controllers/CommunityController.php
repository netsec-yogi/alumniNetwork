<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Post;
use App\Models\Report;
use App\Services\CommunityService;
use App\Services\PostService;
use App\Support\PostPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Communities, chapters and batch groups (SRS 27-28). */
class CommunityController extends Controller
{
    public function __construct(
        private readonly CommunityService $communities,
        private readonly PostService $posts,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember(), 403);

        $filters = $request->validate([
            'kind' => ['nullable', Rule::in([Community::KIND_COMMUNITY, Community::KIND_CHAPTER])],
            'q' => ['nullable', 'string', 'max:100'],
            'mine' => ['nullable', 'boolean'],
        ]);
        $memberships = CommunityMember::where('user_id', $user->id)->pluck('status', 'community_id');

        $groups = Community::query()
            ->when($filters['kind'] ?? null, fn ($q, $k) => $q->where('kind', $k))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where('name', 'like', '%'.addcslashes($t, '%_\\').'%'))
            ->when(! empty($filters['mine']), fn ($q) => $q->whereIn('id', $memberships->keys()))
            // Restricted groups (batches) are only listed to people who can join them.
            ->where(fn ($q) => $q->where('join_policy', '!=', Community::RESTRICTED)->orWhereIn('id', $memberships->keys()))
            ->withCount(['memberships as members_count' => fn ($q) => $q->where('status', CommunityMember::ACTIVE)])
            ->orderByDesc('is_official')->orderByDesc('members_count')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Community $c) => [
                ...$this->card($c),
                'membership' => $memberships[$c->id] ?? null,
            ]);

        return Inertia::render('Communities/Index', [
            'groups' => $groups,
            'filters' => (object) $filters,
        ]);
    }

    public function show(Request $request, Community $community): Response
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember() && ($community->join_policy !== Community::RESTRICTED || $community->membershipOf($user) || $this->communities->isGlobalModerator($user, $community)), 403);

        $membership = $community->membershipOf($user);
        $canRead = $this->communities->canRead($user, $community);

        $query = $this->posts->visibleTo($user)->where('community_id', $community->id)
            ->with(PostPresenter::WITH)->orderByRaw('pinned_at IS NULL')->orderByDesc('pinned_at')->orderByDesc('id');

        return Inertia::render('Communities/Show', [
            'group' => [
                ...$this->card($community),
                'description' => $community->description,
                'leaders' => $community->members()->wherePivotIn('role', [CommunityMember::ADMIN, CommunityMember::MODERATOR])
                    ->with('alumniProfile:id,user_id,verification_status')->limit(12)->get()
                    ->map(fn ($u) => ['name' => $u->name, 'role' => $u->pivot->role, 'profile_id' => $u->alumniProfile?->isVerified() ? $u->alumniProfile->id : null]),
                'members_count' => $community->memberships()->where('status', CommunityMember::ACTIVE)->count(),
                'pending_count' => $this->communities->canModerate($user, $community) ? $community->memberships()->where('status', CommunityMember::PENDING)->count() : 0,
            ],
            'membership' => $membership ? ['status' => $membership->status, 'role' => $membership->role] : null,
            'canRead' => $canRead,
            'canPost' => (bool) $membership?->isActive() || $this->communities->isGlobalModerator($user, $community),
            'canModerate' => $this->communities->canModerate($user, $community),
            'reportReasons' => Report::REASONS,
            'events' => $community->events()->published()->upcoming()->orderBy('starts_at')->limit(4)->get()
                ->map(fn (Event $e) => ['slug' => $e->slug, 'title' => $e->title, 'starts_at' => $e->starts_at->format('D j M, g:i A')]),
            'posts' => $canRead ? Inertia::scroll(function () use ($query, $user) {
                $page = $query->cursorPaginate(15);
                $presenter = new PostPresenter($user, $page->items());

                return $page->through(fn (Post $p) => $presenter->present($p));
            }) : null,
        ]);
    }

    public function join(Request $request, Community $community): RedirectResponse
    {
        try {
            $m = $this->communities->join($request->user(), $community);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $m->status === CommunityMember::PENDING ? 'Request sent to the group admins.' : "Welcome to {$community->name}!");
    }

    public function leave(Request $request, Community $community): RedirectResponse
    {
        try {
            $this->communities->leave($request->user(), $community);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "You left {$community->name}.");
    }

    public function members(Request $request, Community $community): Response
    {
        $user = $request->user();
        abort_unless($this->communities->canModerate($user, $community), 403);
        $status = $request->validate(['status' => ['nullable', Rule::in(['active', 'pending', 'banned'])]])['status'] ?? 'active';

        return Inertia::render('Communities/Members', [
            'group' => $this->card($community),
            'status' => $status,
            'isAdmin' => $this->communities->isAdmin($user, $community),
            'members' => $community->memberships()->where('status', $status)
                ->with(['user:id,name,email', 'user.alumniProfile:id,user_id,programme_id,graduation_year', 'user.alumniProfile.programme:id,code'])
                ->orderByRaw("FIELD(role, 'admin', 'moderator', 'member')")->orderBy('created_at')
                ->paginate(50)->withQueryString()
                ->through(fn (CommunityMember $m) => [
                    'id' => $m->id,
                    'name' => $m->user->name,
                    'subtitle' => $m->user->alumniProfile ? "{$m->user->alumniProfile->programme->code} · {$m->user->alumniProfile->graduation_year}" : $m->user->email,
                    'role' => $m->role,
                    'is_me' => $m->user_id === $user->id,
                    'joined' => $m->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function manage(Request $request, CommunityMember $member): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'promote', 'demote', 'make_admin', 'ban', 'remove'])]])['action'];

        try {
            $this->communities->manage($request->user(), $member, $action);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Done.');
    }

    /** @return array<string, mixed> */
    private function card(Community $c): array
    {
        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->name,
            'kind' => $c->kind,
            'category' => Community::CATEGORIES[$c->kind][$c->category] ?? $c->category,
            'join_policy' => $c->join_policy,
            'is_official' => $c->is_official,
            'members_count' => $c->members_count ?? null,
        ];
    }
}
