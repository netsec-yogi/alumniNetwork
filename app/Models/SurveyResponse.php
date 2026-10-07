<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyResponse extends Model
{
    public const UPDATED_AT = null;

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}
