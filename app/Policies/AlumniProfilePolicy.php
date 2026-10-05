<?php

namespace App\Policies;

use App\Models\AlumniProfile;
use App\Models\User;
use App\Services\ConnectionService;

class AlumniProfilePolicy
{
    /**
     * The directory is open to verified alumni, students, faculty and alumni
     * staff -- not to unverified registrations, which would otherwise make
     * scraping it as easy as signing up.
     */
    public function viewAny(User $viewer): bool
    {
        return $viewer->isCommunityMember();
    }

    public function view(User $viewer, AlumniProfile $profile): bool
    {
        if ($viewer->id === $profile->user_id) {
            return true;
        }

        return $this->viewAny($viewer)
            && $profile->isVerified()
            && ! app(ConnectionService::class)->isBlockedEitherWay($viewer->id, $profile->user_id);
    }

    /** Connect, follow, block or report from a profile page. */
    public function interact(User $viewer, AlumniProfile $profile): bool
    {
        return $viewer->id !== $profile->user_id && $this->viewAny($viewer) && $profile->isVerified();
    }

    /** Alumni edit only their own profile (SRS 104, test 3). */
    public function update(User $viewer, AlumniProfile $profile): bool
    {
        return $viewer->id === $profile->user_id;
    }
}
