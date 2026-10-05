<?php

namespace App\Notifications;

use App\Models\MentorshipRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorshipRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly MentorshipRequest $mentorship) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Mentoring request from {$this->mentorship->mentee->name}")
            ->line("{$this->mentorship->mentee->name} would like you to mentor them (".(config('mentoring.categories')[$this->mentorship->category] ?? $this->mentorship->category).').')
            ->action('Review request', route('mentoring.index', ['tab' => 'mentoring']));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->mentorship->mentee->name} asked you to be their mentor",
            'url' => route('mentoring.index', ['tab' => 'mentoring'], false),
        ];
    }
}
