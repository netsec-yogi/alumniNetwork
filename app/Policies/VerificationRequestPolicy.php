<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\VerificationRequest;

class VerificationRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::AlumniVerify->value);
    }

    /** Separation of duties: nobody verifies their own alumni claim. */
    public function decide(User $actor, VerificationRequest $request): bool
    {
        return $actor->can(Permission::AlumniVerify->value)
            && $request->profile->user_id !== $actor->id;
    }
}
