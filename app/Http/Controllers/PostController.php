<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\Report;
use App\Services\AuditLogger;
use App\Services\ConnectionService;
use App\Services\PostService;
use App\Support\PostPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly ConnectionService $connections,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request, Post $post): Response
    {
        $this->authorize('view', $post);
        $user = $request->user();
        $post->load(PostPresenter::WITH);
        $blocked = $this->connections->blockedIds($user->id);
        $moderates = $user->can('pin', $post);

        $comments = $post->comments()
            ->where('status', Post::PUBLISHED)
            ->whereNotIn('user_id', $blocked)
            ->with(['author:id,name', 'author.alumniProfile:id,user_id,verification_status,preferred_name'])
            ->oldest()
            ->paginate(50)
            ->through(fn (PostComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'author' => [
                    'name' => $c->author->alumniProfile?->preferred_name ?: $c->author->name,
                    'profile_id' => $c->author->alumniProfile?->isVerified() ? $c->author->alumniProfile->id : null,
                ],
                'at' => $c->created_at->diffForHumans(),
                'can_delete' => $c->user_id === $user->id || $moderates,
            ]);

        return Inertia::render('Feed/Show', [
            'post' => (new PostPresenter($user, [$post]))->present($post),
            'comments' => $comments,
            'reportReasons' => Report::REASONS,
        ]);
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        if ($post->user_id !== $request->user()->id) {
            $post->removeByModerator($request->user(), 'Removed by moderator');
            $this->audit->record('post.removed', 'communities', $post);
        } else {
            $post->delete();
        }

        return $request->header('referer') === route('posts.show', $post)
            ? redirect()->route('feed')->with('success', 'Post deleted.')
            : back()->with('success', 'Post deleted.');
    }

    /** Toggles redirect back; the UI updates optimistically and reloads nothing. */
    public function like(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('interact', $post);
        $this->posts->toggleLike($request->user(), $post);

        return back();
    }

    public function save(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('view', $post);
        $this->posts->toggleSave($request->user(), $post);

        return back();
    }

    public function pin(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('pin', $post);
        $post->forceFill(['pinned_at' => $post->pinned_at ? null : now()])->save();

        return back()->with('success', $post->pinned_at ? 'Pinned.' : 'Unpinned.');
    }

    public function comment(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('interact', $post);
        $body = $request->validate(['body' => ['required', 'string', 'max:2000']])['body'];
        $this->posts->comment($request->user(), $post, $body);

        return back();
    }

    public function destroyComment(Request $request, PostComment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);
        $this->posts->deleteComment($comment);

        return back();
    }
}
