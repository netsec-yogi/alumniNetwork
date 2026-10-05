<?php

namespace App\Policies;

use App\Models\PostComment;
use App\Models\User;

class PostCommentPolicy
{
    public function __construct(private readonly PostPolicy $posts) {}

    public function view(User $user, PostComment $comment): bool
    {
        return $this->posts->view($user, $comment->post);
    }

    public function delete(User $user, PostComment $comment): bool
    {
        return $comment->user_id === $user->id || $this->posts->moderates($user, $comment->post);
    }
}
