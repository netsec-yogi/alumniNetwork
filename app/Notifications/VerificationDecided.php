<?php

namespace App\Notifications;

use App\Enums\VerificationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly VerificationStatus $decision,
        private readonly ?string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},");

        if ($this->decision === VerificationStatus::Verified) {
            return $mail
                ->subject('Your alumni account is verified')
                ->line('Your ABV-IIITM alumni status has been verified. The alumni directory and network are now open to you.')
                ->action('Go to Alumni Connect', route('dashboard'));
        }

        return $mail
            ->subject('About your alumni verification')
            ->line('We could not verify your alumni status from the details provided.')
            ->line('Reason: '.$this->reason)
            ->line('You can update your details and contact the alumni office if you believe this is a mistake.')
            ->action('Review your details', route('dashboard'));
    }
}
