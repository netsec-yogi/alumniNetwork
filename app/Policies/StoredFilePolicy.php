<?php

namespace App\Policies;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Download authorisation (SRS 72): no file is reachable by URL alone. */
class StoredFilePolicy
{
    public function view(?User $user, StoredFile $file): bool
    {
        if ($user && $file->owner_id === $user->id) {
            return true;
        }

        return match ($file->visibility) {
            StoredFile::PUBLIC => true,
            StoredFile::MEMBERS => (bool) $user?->isCommunityMember(),
            // Private files follow whatever they are attached to.
            default => $user !== null && $file->attachable !== null && Gate::forUser($user)->allows('view', $file->attachable),
        };
    }
}
