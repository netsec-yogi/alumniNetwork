<?php

namespace App\Notifications;

use App\Models\Connection;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConnectionRequested extends Notification implements ShouldQueue
{
    use Queueable;

    // Not $connection: Queueable already uses that name for the queue connection.
    public function __construct(private readonly User $from, private readonly Connection $request) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->from->name} wants to connect")
            ->line("{$this->from->name} sent you a connection request on ABV-IIITM Alumni Connect.")
            ->action('Review request', route('connections.index', ['tab' => 'received']));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->from->name} wants to connect",
            'body' => $this->request->message,
            'url' => route('connections.index', ['tab' => 'received'], false),
        ];
    }
}
