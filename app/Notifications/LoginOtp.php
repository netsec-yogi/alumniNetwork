<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One-time sign-in code. Deliberately not queued: EmailOtpLogin sends it
 * synchronously so a delivery failure can invalidate the code at once.
 */
class LoginOtp extends Notification
{
    public function __construct(private readonly string $code, private readonly int $validMinutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your ABV-IIITM Alumni Portal Login OTP')
            ->greeting('Dear '.$notifiable->name.',')
            ->line('Your One-Time Password (OTP) for logging in to the ABV-IIITM Alumni Portal is:')
            ->line('**'.$this->code.'**')
            ->line("This OTP is valid for {$this->validMinutes} minutes.")
            ->line('Please do not share this OTP with anyone.')
            ->line('If you did not request this OTP, please ignore this email.')
            ->salutation("Regards,\nABV-IIITM Alumni Portal");
    }
}
