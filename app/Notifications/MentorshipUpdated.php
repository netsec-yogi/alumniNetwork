<?php

namespace App\Notifications;

use App\Models\MentorshipRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorshipUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly MentorshipRequest $mentorship) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function headline(): string
    {
        $mentor = $this->mentorship->mentor->name;

        return match ($this->mentorship->status) {
            MentorshipRequest::ACCEPTED => "{$mentor} agreed to mentor you",
            MentorshipRequest::DECLINED => "{$mentor} can’t take on your mentoring request",
            MentorshipRequest::COMPLETED => 'Your mentorship has been marked complete',
            default => 'Mentoring update',
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->headline())->line($this->headline().'.');
        if ($this->mentorship->mentor_note) {
            $mail->line('Note: '.$this->mentorship->mentor_note);
        }

        return $mail->action('Open mentoring', route('mentoring.index'));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->headline(), 'body' => $this->mentorship->mentor_note, 'url' => route('mentoring.index', [], false)];
    }
}
