<?php

namespace App\Notifications;

use App\Models\JobPosting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobModerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly JobPosting $job) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function approved(): bool
    {
        return $this->job->status === JobPosting::APPROVED;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(($this->approved() ? 'Your posting is live: ' : 'Your posting was not approved: ').$this->job->title);

        return $this->approved()
            ? $mail->line("Your posting for {$this->job->title} at {$this->job->organization} is now visible to the IIITM community.")->action('View posting', route('jobs.show', $this->job))
            : $mail->line("Your posting for {$this->job->title} was not approved.")->line('Reason: '.$this->job->rejection_reason);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => ($this->approved() ? 'Your posting is live: ' : 'Posting not approved: ').$this->job->title,
            'body' => $this->approved() ? null : $this->job->rejection_reason,
            'url' => route('jobs.show', $this->job, false),
        ];
    }
}
