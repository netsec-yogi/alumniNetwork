<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Services\CommunityService;
use App\Services\ConnectionService;

class PostPolicy
{
    public function __construct(
        private readonly CommunityService $communities,
        private readonly ConnectionService $connections,
    ) {}

    public function view(User $user, Post $post): bool
    {
        if ($this->moderates($user, $post)) {
            return true;
        }

        if ($post->status !== Post::PUBLISHED || ! $user->isCommunityMember()) {
            return false;
        }
        if ($this->connections->isBlockedEitherWay($user->id, $post->user_id)) {
            return false;
        }

        return $post->community_id === null || $this->communities->canRead($user, $post->community);
    }

    public function interact(User $user, Post $post): bool
    {
        return $post->status === Post::PUBLISHED
            && $this->view($user, $post)
            && ($post->community_id === null || $this->communities->isActiveMember($user, $post->community) || $this->moderates($user, $post));
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id || $this->moderates($user, $post);
    }

    public function pin(User $user, Post $post): bool
    {
        return $this->moderates($user, $post);
    }

    public function moderates(User $user, Post $post): bool
    {
        return $post->community_id
            ? $this->communities->canModerate($user, $post->community)
            : $user->can('communities.moderate');
    }
}
