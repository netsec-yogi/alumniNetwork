<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\JobPosting;
use App\Models\User;

class JobPostingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isCommunityMember() || $user->hasRole(RoleName::Recruiter->value);
    }

    public function view(User $user, JobPosting $job): bool
    {
        return $job->posted_by === $user->id
            || $user->can(Permission::JobsModerate->value)
            || ($job->isLive() && $this->viewAny($user));
    }

    /** Alumni, faculty, recruiters and career staff post; students do not. */
    public function create(User $user): bool
    {
        return (bool) $user->alumniProfile?->isVerified()
            || $user->hasAnyRole([RoleName::Faculty->value, RoleName::Recruiter->value])
            || $user->can(Permission::JobsModerate->value);
    }

    public function update(User $user, JobPosting $job): bool
    {
        return $user->can(Permission::JobsModerate->value)
            || ($job->posted_by === $user->id && in_array($job->status, [JobPosting::PENDING, JobPosting::APPROVED], true));
    }

    public function close(User $user, JobPosting $job): bool
    {
        return $job->status !== JobPosting::CLOSED
            && ($job->posted_by === $user->id || $user->can(Permission::JobsModerate->value));
    }

    public function moderate(User $user): bool
    {
        return $user->can(Permission::JobsModerate->value);
    }

    public function requestReferral(User $user, JobPosting $job): bool
    {
        return $job->posted_by !== $user->id && $user->isCommunityMember() && $job->referral_available && $job->isLive();
    }
}
