<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Security notice + reset link after an administrator reset. The link is a
 * standard password-broker token (expires per config/auth.php); the email
 * contains no password.
 */
class PasswordResetByAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Your Alumni Connect password was reset by an administrator')
            ->line('An administrator reset the password on your ABV-IIITM Alumni Connect account. Your old password no longer works, and you have been signed out on all devices.')
            ->line('Choose a new password using the button below.')
            ->action('Choose a new password', route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]))
            ->line("This link expires in {$minutes} minutes. You can request a new one from the sign-in page.")
            ->line('If you did not expect this, contact the Alumni Portal administrator straight away.');
    }
}
