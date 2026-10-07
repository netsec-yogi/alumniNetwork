<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A simple in-app notice (optionally emailed) for low-ceremony events. */
class Notice extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  string  $url  a local path, e.g. route('x', [], false) */
    public function __construct(
        private readonly string $title,
        private readonly string $url,
        private readonly ?string $body = null,
        private readonly bool $email = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->line($this->title);
        if ($this->body) {
            $mail->line($this->body);
        }

        return $mail->action('Open Alumni Connect', url($this->url));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
