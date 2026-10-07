<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MessageRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly User $from, private readonly Conversation $conversation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->from->name} sent you a message request",
            'url' => route('messages.show', $this->conversation, false),
        ];
    }
}
