<?php

namespace App\Contracts;

use App\Models\User;

/** Content a moderator can take down in response to a report. */
interface Moderatable
{
    public function removeByModerator(User $moderator, string $reason): void;

    /** A short, plain-text excerpt for the moderation queue. */
    public function moderationSummary(): string;
}
