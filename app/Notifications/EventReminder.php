<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminder extends Notification implements ShouldQueue
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
            ->subject("Tomorrow: {$this->event->title}")
            ->line("A reminder that {$this->event->title} starts {$this->event->starts_at->format('l \\a\\t g:i A')}.")
            ->line($this->event->is_online ? 'The joining link is on your ticket page.' : "Venue: {$this->event->venue}")
            ->action('View ticket', route('events.ticket', $this->event));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Reminder: {$this->event->title}",
            'body' => $this->event->starts_at->format('D j M, g:i A'),
            'url' => route('events.ticket', $this->event, false),
        ];
    }
}
