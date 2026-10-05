<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One engagement event (SRS 52); the source of every engagement metric. */
#[Fillable(['alumni_profile_id', 'activity_type', 'activity_date', 'entity_type', 'entity_id', 'engagement_mode', 'metadata'])]
class EngagementActivity extends Model
{
    public const MODE_PHILANTHROPIC = 'philanthropic';

    public const MODE_VOLUNTEER = 'volunteer';

    public const MODE_EXPERIENTIAL = 'experiential';

    public const MODE_COMMUNICATION = 'communication';

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AlumniProfile::class, 'alumni_profile_id');
    }
}
