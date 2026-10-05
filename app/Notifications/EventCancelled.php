<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Event $event) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Cancelled: {$this->event->title}")
            ->line("We’re sorry — {$this->event->title} on {$this->event->starts_at->format('j F')} has been cancelled.")
            ->line('Reason: '.$this->event->cancellation_reason);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Cancelled: {$this->event->title}",
            'body' => $this->event->cancellation_reason,
            'url' => route('events.show', $this->event, false),
        ];
    }
}
