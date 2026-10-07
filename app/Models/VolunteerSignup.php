<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Status, hours and approval are set by VolunteeringController only. */
#[Fillable(['volunteer_opportunity_id', 'user_id'])]
class VolunteerSignup extends Model
{
    protected function casts(): array
    {
        return ['hours' => 'float', 'approved_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'volunteer_opportunity_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
