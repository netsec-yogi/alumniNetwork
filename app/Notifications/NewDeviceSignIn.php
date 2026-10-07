<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sign-in from a browser this account hasn't used before. */
class NewDeviceSignIn extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ?string $ip,
        private readonly string $browser,
        private readonly string $at,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New sign-in to your Alumni Connect account')
            ->line('Your ABV-IIITM Alumni Connect account was just signed in to from a new browser or device.')
            ->line("When: {$this->at}")
            ->line('IP address: '.($this->ip ?? 'unknown'))
            ->line("Browser: {$this->browser}")
            ->line('If this was you, there is nothing to do. If not, sign out other sessions and change your password now.')
            ->action('Review active sessions', route('profile.sessions'));
    }
}
