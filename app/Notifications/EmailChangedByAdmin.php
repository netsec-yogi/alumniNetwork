<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Sent to the OLD address when an administrator changes a member's email. */
class EmailChangedByAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $newEmail) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Only a masked hint of the new address goes to the old one.
        [$local, $domain] = explode('@', $this->newEmail) + [1 => ''];

        return (new MailMessage)
            ->subject('The email on your Alumni Connect account was changed')
            ->line('An administrator changed the email address on your ABV-IIITM Alumni Connect account to '.Str::mask($local, '*', 2).'@'.$domain.'.')
            ->line('If you did not ask for this, contact the alumni office straight away.');
    }
}
