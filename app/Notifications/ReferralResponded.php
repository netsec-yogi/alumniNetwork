<?php

namespace App\Notifications;

use App\Models\JobReferralRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralResponded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly JobReferralRequest $referral) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function accepted(): bool
    {
        return $this->referral->status === JobReferralRequest::ACCEPTED;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject(($this->accepted() ? 'Referral accepted: ' : 'Referral update: ').$this->referral->job->title);
        $mail->line($this->accepted()
            ? "{$this->referral->job->poster->name} agreed to refer you for {$this->referral->job->title}."
            : "{$this->referral->job->poster->name} isn’t able to refer you for {$this->referral->job->title} this time.");

        if ($this->referral->response_note) {
            $mail->line('Note: '.$this->referral->response_note);
        }

        return $mail->action('View posting', route('jobs.show', $this->referral->job));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => ($this->accepted() ? 'Referral accepted: ' : 'Referral declined: ').$this->referral->job->title,
            'body' => $this->referral->response_note,
            'url' => route('jobs.referrals', ['tab' => 'sent'], false),
        ];
    }
}
