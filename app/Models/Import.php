<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A bulk import run. All fields are set by AlumniRecordImporter. */
class Import extends Model
{
    protected function casts(): array
    {
        return ['errors' => 'array', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
