<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PromotedFromWaitlist extends Notification implements ShouldQueue
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
            ->subject("A seat opened up: {$this->event->title}")
            ->line("Good news — you have moved off the waitlist and are now registered for {$this->event->title}.")
            ->line('If you can no longer attend, please cancel so the next person gets your seat.')
            ->action('View ticket', route('events.ticket', $this->event));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "You’re off the waitlist for {$this->event->title}",
            'url' => route('events.ticket', $this->event, false),
        ];
    }
}
