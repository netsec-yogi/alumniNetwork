<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Privilege-escalation rules for role changes (SRS 79).
 *
 * An administrator may grant only roles whose every permission they hold
 * themselves, may never change their own roles, and may not touch an
 * account that holds permissions they lack. Only a Super Administrator can
 * create another.
 */
class RoleAssignment
{
    /** @return Collection<int, Role> */
    public function assignableBy(User $actor): Collection
    {
        $held = $actor->getAllPermissions()->pluck('name');

        return Role::with('permissions')->orderBy('name')->get()->filter(function (Role $role) use ($actor, $held) {
            if ($role->name === RoleName::SuperAdmin->value && ! $actor->hasRole(RoleName::SuperAdmin->value)) {
                return false;
            }

            return $role->permissions->pluck('name')->diff($held)->isEmpty();
        })->values();
    }

    /** Whether $actor outranks (or equals) $target in permissions. */
    public function canManage(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if ($target->hasRole(RoleName::SuperAdmin->value) && ! $actor->hasRole(RoleName::SuperAdmin->value)) {
            return false;
        }

        return $target->getAllPermissions()->pluck('name')
            ->diff($actor->getAllPermissions()->pluck('name'))
            ->isEmpty();
    }

    /**
     * Replace the target's roles. Only roles the actor may grant can be
     * added or removed; others the target holds are left untouched.
     *
     * @param  list<string>  $requested
     */
    public function sync(User $actor, User $target, array $requested): void
    {
        $assignable = $this->assignableBy($actor)->pluck('name');
        $requested = collect($requested)->intersect($assignable);

        $untouchable = $target->getRoleNames()->diff($assignable);

        DB::transaction(fn () => $target->syncRoles($requested->merge($untouchable)->unique()->values()->all()));
    }
}
