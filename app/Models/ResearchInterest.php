<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['research_opportunity_id', 'user_id', 'note'])]
class ResearchInterest extends Model
{
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(ResearchOpportunity::class, 'research_opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
