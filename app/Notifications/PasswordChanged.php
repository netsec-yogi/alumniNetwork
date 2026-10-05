<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** SRS 15: tell the owner whenever their password changes. */
class PasswordChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $ip) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Alumni Connect password was changed')
            ->line('The password for your ABV-IIITM Alumni Connect account was just changed'.($this->ip ? " from IP address {$this->ip}." : '.'))
            ->line('Other devices have been signed out.')
            ->line('If you did not make this change, reset your password immediately and contact the alumni office.')
            ->action('Reset password', route('password.request'));
    }
}
