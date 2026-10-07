<?php

namespace App\Support;

use App\Models\Event;
use App\Models\JobPosting;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Shapes posts for the feed, with the viewer's likes, saves and permissions. */
class PostPresenter
{
    /** @var Collection<int, int> */
    private Collection $liked;

    /** @var Collection<int, int> */
    private Collection $saved;

    /** @param  iterable<Post>  $posts */
    public function __construct(private readonly User $viewer, iterable $posts)
    {
        $ids = collect($posts)->pluck('id');
        $this->liked = DB::table('post_likes')->where('user_id', $viewer->id)->whereIn('post_id', $ids)->pluck('post_id');
        $this->saved = DB::table('post_saves')->where('user_id', $viewer->id)->whereIn('post_id', $ids)->pluck('post_id');
    }

    /** Relations a feed query should eager-load. */
    public const WITH = ['author:id,name', 'author.alumniProfile:id,user_id,programme_id,graduation_year,verification_status,preferred_name,photo_file_id', 'author.alumniProfile.programme:id,code', 'author.alumniProfile.photo', 'community:id,name,slug', 'shareable'];

    /** @return array<string, mixed> */
    public function present(Post $post): array
    {
        $profile = $post->author->alumniProfile;

        return [
            'id' => $post->id,
            'kind' => $post->kind,
            'body' => $post->body,
            'link_url' => $post->link_url,
            'author' => [
                'name' => $profile?->preferred_name ?: $post->author->name,
                'subtitle' => $profile ? "{$profile->programme->code} · {$profile->graduation_year}" : null,
                'profile_id' => $profile?->isVerified() ? $profile->id : null,
                'photo_url' => $profile?->photo?->url(true),
            ],
            'community' => $post->community ? ['name' => $post->community->name, 'slug' => $post->community->slug] : null,
            'shared' => $this->shared($post),
            'pinned' => $post->pinned_at !== null,
            'likes' => $post->likes_count,
            'comments' => $post->comments_count,
            'liked' => $this->liked->contains($post->id),
            'saved' => $this->saved->contains($post->id),
            'at' => $post->created_at->diffForHumans(),
            'can' => [
                'delete' => Gate::forUser($this->viewer)->allows('delete', $post),
                'pin' => Gate::forUser($this->viewer)->allows('pin', $post),
                'interact' => Gate::forUser($this->viewer)->allows('interact', $post),
            ],
        ];
    }

    /** @return array<string, string>|null */
    private function shared(Post $post): ?array
    {
        $item = $post->shareable;

        return match (true) {
            $item instanceof Event => ['kind' => 'Event', 'title' => $item->title, 'subtitle' => $item->starts_at->format('D j M, g:i A'), 'url' => route('events.show', $item, false)],
            $item instanceof JobPosting => ['kind' => $item->type === 'internship' ? 'Internship' : 'Job', 'title' => $item->title, 'subtitle' => $item->organization, 'url' => route('jobs.show', $item, false)],
            default => null,
        };
    }
}
