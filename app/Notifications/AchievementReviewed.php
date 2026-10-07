<?php

namespace App\Notifications;

use App\Models\Achievement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AchievementReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Achievement $achievement) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $published = $this->achievement->status === Achievement::PUBLISHED;

        return [
            'title' => ($published ? 'Your achievement is published: ' : 'Your achievement was not published: ').$this->achievement->title,
            'body' => $published ? null : $this->achievement->rejection_reason,
            'url' => route('achievements.mine', [], false),
        ];
    }
}
