<?php

namespace App\Notifications;

use App\Models\JobReferralRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly JobReferralRequest $referral) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Referral request: {$this->referral->job->title}")
            ->line("{$this->referral->requester->name} asked for a referral for {$this->referral->job->title} at {$this->referral->job->organization}.")
            ->line('You decide whether to refer them.')
            ->action('Review request', route('jobs.referrals'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "{$this->referral->requester->name} asked for a referral",
            'body' => $this->referral->job->title,
            'url' => route('jobs.referrals', [], false),
        ];
    }
}
