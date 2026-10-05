<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /** Public events are visible to guests; member events to the community. */
    public function view(?User $user, Event $event): bool
    {
        if ($user?->can(Permission::EventsView->value)) {
            return true;
        }

        if ($event->status === Event::DRAFT) {
            return false;
        }

        return $event->audience === Event::AUDIENCE_PUBLIC || ($user?->isCommunityMember() ?? false);
    }

    public function register(User $user, Event $event): bool
    {
        return $this->view($user, $event)
            && $user->hasVerifiedEmail()
            && ($event->audience === Event::AUDIENCE_PUBLIC || $user->isCommunityMember());
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::EventsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::EventsCreate->value);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can(Permission::EventsUpdate->value);
    }

    /** Only drafts can be deleted; published events are cancelled instead. */
    public function delete(User $user, Event $event): bool
    {
        return $user->can(Permission::EventsDelete->value) && $event->status === Event::DRAFT;
    }

    public function manageAttendance(User $user, Event $event): bool
    {
        return $user->can(Permission::EventsManageAttendance->value);
    }
}
