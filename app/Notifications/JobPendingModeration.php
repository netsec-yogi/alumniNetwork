<?php

namespace App\Notifications;

use App\Models\JobPosting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class JobPendingModeration extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly JobPosting $job) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Job awaiting review: {$this->job->title}",
            'body' => $this->job->organization,
            'url' => route('admin.jobs.index', [], false),
        ];
    }
}
