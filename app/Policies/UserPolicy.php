<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Services\RoleAssignment;

class UserPolicy
{
    public function __construct(private readonly RoleAssignment $roles) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    /** Suspend, reactivate, unlock. Never yourself, never someone above you. */
    public function manage(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersManage->value) && $this->roles->canManage($actor, $target);
    }

    /**
     * Set or reset someone else's password: needs the dedicated permission
     * AND the privilege ceiling. Never one's own (that's the Security page).
     */
    public function managePassword(User $actor, User $target): bool
    {
        return $actor->id !== $target->id
            && $actor->can(Permission::UsersPasswordManage->value)
            && $this->roles->canManage($actor, $target);
    }

    public function assignRoles(User $actor, User $target): bool
    {
        return $actor->can(Permission::RolesManage->value) && $this->roles->canManage($actor, $target);
    }
}
