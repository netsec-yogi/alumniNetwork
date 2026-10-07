<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/** Participants only. Deliberately no exception for administrators (SRS 25). */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }
}
