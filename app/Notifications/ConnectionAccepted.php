<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ConnectionAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly User $by) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->by->name} accepted your connection request",
            'url' => $this->by->alumniProfile ? route('alumni.show', $this->by->alumniProfile, false) : route('connections.index', [], false),
        ];
    }
}
