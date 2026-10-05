<?php

namespace App\Notifications;

use App\Models\Community;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CommunityJoinRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Community $community, private readonly User $requester) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->requester->name} asked to join {$this->community->name}",
            'url' => route('communities.members', $this->community, false),
        ];
    }
}
