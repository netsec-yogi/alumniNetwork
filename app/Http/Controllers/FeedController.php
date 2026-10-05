<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Post;
use App\Models\Report;
use App\Services\ConnectionService;
use App\Services\PostService;
use App\Support\PostPresenter;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** The social feed (SRS 26). */
class FeedController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly ConnectionService $connections,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember(), 403);
        $tab = $request->validate(['tab' => ['nullable', Rule::in(['all', 'following', 'saved'])]])['tab'] ?? 'all';

        $query = $this->posts->visibleTo($user)
            ->when($tab === 'following', fn ($q) => $q->whereIn('user_id', $user->following()->pluck('users.id')->merge($this->connections->connectedIds($user->id))))
            ->when($tab === 'saved', fn ($q) => $q->whereIn('id', fn ($s) => $s->select('post_id')->from('post_saves')->where('user_id', $user->id)))
            ->with(PostPresenter::WITH)
            ->orderByDesc('id');

        return Inertia::render('Feed/Index', [
            'tab' => $tab,
            'posts' => Inertia::scroll(function () use ($query, $user) {
                $page = $query->cursorPaginate(15);
                $presenter = new PostPresenter($user, $page->items());

                return $page->through(fn (Post $p) => $presenter->present($p));
            }),
            'myCommunities' => $user->isCommunityMember()
                ? Community::whereHas('memberships', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))->orderBy('name')->get(['id', 'name'])
                : [],
            'canAnnounce' => $user->can('communities.moderate'),
            'reportReasons' => Report::REASONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember(), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'link_url' => ['nullable', 'url:https', 'max:500'],
            'kind' => ['nullable', Rule::in(array_keys(Post::KINDS))],
            'community_id' => ['nullable', 'integer', 'exists:communities,id'],
            'share_type' => ['nullable', Rule::in(['event', 'job_posting'])],
            'share_id' => ['required_with:share_type', 'nullable', 'integer'],
        ]);

        $community = isset($data['community_id']) ? Community::findOrFail($data['community_id']) : null;
        $shareable = null;
        if (! empty($data['share_type'])) {
            $shareable = Relation::getMorphedModel($data['share_type'])::findOrFail($data['share_id']);
            $this->authorize('view', $shareable);
        }

        try {
            $this->posts->create($user, $data, $community, $shareable);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Posted.');
    }
}
