<?php

namespace App\Services;

use App\Models\AlumniProfile;
use App\Models\EngagementActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Writes engagement activities (SRS 52). Only alumni accrue engagement;
 * calls for other users are no-ops. Idempotent per (alumnus, type, entity),
 * so retries and double-clicks never inflate the record.
 */
class EngagementRecorder
{
    public function record(
        User|AlumniProfile|null $who,
        string $type,
        string $mode,
        ?Model $entity = null,
        ?Carbon $date = null,
        array $metadata = [],
    ): ?EngagementActivity {
        $profile = $who instanceof User ? $who->alumniProfile : $who;

        if ($profile === null) {
            return null;
        }

        return EngagementActivity::firstOrCreate(
            [
                'alumni_profile_id' => $profile->id,
                'activity_type' => $type,
                'entity_type' => $entity?->getMorphClass(),
                'entity_id' => $entity?->getKey(),
            ],
            [
                'activity_date' => ($date ?? now())->toDateString(),
                'engagement_mode' => $mode,
                'metadata' => $metadata ?: null,
            ],
        );
    }
}
