<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Achievement;
use App\Models\User;

class AchievementPolicy
{
    public function view(?User $user, Achievement $achievement): bool
    {
        return $achievement->status === Achievement::PUBLISHED
            || ($user && ($achievement->alumni_profile_id === $user->alumniProfile?->id || $user->can(Permission::ContentManage->value)));
    }
}
