<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    /** Used for attachments and for reporting: participants only. */
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->hasParticipant($user);
    }

    public function delete(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id;
    }
}
