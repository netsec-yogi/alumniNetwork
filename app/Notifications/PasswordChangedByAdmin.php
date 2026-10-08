<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Security notice: an administrator set a new password. Never contains the password. */
class PasswordChangedByAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Alumni Connect password was changed by an administrator')
            ->line('An administrator changed the password on your ABV-IIITM Alumni Connect account, and you have been signed out on all devices.')
            ->line('The administrator will give you the new password through a separate, trusted channel. You may be asked to choose your own password when you next sign in.')
            ->line('If you did not expect this, contact the Alumni Portal administrator straight away.')
            ->action('Sign in', route('login'));
    }
}
