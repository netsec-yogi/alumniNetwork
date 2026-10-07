<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Donation;
use App\Models\User;

class DonationPolicy
{
    /** Donors see their own; fundraising staff see all. Used for receipt downloads. */
    public function view(User $user, Donation $donation): bool
    {
        return $donation->user_id === $user->id || $user->can(Permission::DonationsView->value);
    }
}
