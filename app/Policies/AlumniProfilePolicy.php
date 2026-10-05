<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\AlumniProfile;
use App\Models\User;

class AlumniProfilePolicy
{
    /**
     * The directory is open to verified alumni, students, faculty and alumni
     * staff -- not to unverified registrations, which would otherwise make
     * scraping it as easy as signing up.
     */
    public function viewAny(User $viewer): bool
    {
        return $viewer->alumniProfile?->isVerified()
            || $viewer->hasAnyRole([RoleName::Student->value, RoleName::Faculty->value])
            || $viewer->can(Permission::AlumniView->value);
    }

    public function view(User $viewer, AlumniProfile $profile): bool
    {
        if ($viewer->id === $profile->user_id) {
            return true;
        }

        return $this->viewAny($viewer) && $profile->isVerified();
    }

    /** Alumni edit only their own profile (SRS 104, test 3). */
    public function update(User $viewer, AlumniProfile $profile): bool
    {
        return $viewer->id === $profile->user_id;
    }
}
