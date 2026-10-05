<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventRegistrationConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Event $event, private readonly string $status) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function waitlisted(): bool
    {
        return $this->status === EventRegistration::WAITLISTED;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(($this->waitlisted() ? 'Waitlisted: ' : 'You’re registered: ').$this->event->title)
            ->line($this->event->title)
            ->line($this->event->starts_at->format('l, j F Y · g:i A').($this->event->venue ? " · {$this->event->venue}" : ''));

        return $this->waitlisted()
            ? $mail->line('The event is full, so you are on the waitlist. We will email you if a seat opens up.')->action('View event', route('events.show', $this->event))
            : $mail->line('Show the QR code on your ticket at the entrance.')->action('View ticket', route('events.ticket', $this->event));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => ($this->waitlisted() ? 'Waitlisted for ' : 'Registered for ').$this->event->title,
            'body' => $this->event->starts_at->format('D j M, g:i A'),
            'url' => route('events.show', $this->event, false),
        ];
    }
}
