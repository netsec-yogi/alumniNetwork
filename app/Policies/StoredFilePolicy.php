<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Content\Branding;
use App\Services\PublicMedia;
use Illuminate\Support\Facades\Gate;

/** Download authorisation (SRS 72): no file is reachable by URL alone. */
class StoredFilePolicy
{
    public function view(?User $user, StoredFile $file): bool
    {
        if ($user && $file->owner_id === $user->id) {
            return true;
        }

        // Draft logos: visible to whoever manages branding, for the preview.
        if ($file->purpose === Branding::PURPOSE && $user?->can(Permission::PortalBrandingManage->value)) {
            return true;
        }

        // Content-derived publicness, evaluated now (so unpublishing revokes access at once).
        if ($file->visibility !== StoredFile::PUBLIC && app(PublicMedia::class)->isPublic($file)) {
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
