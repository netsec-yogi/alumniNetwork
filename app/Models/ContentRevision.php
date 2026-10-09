<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A published version of an editable site area (landing text, branding), kept for restore. */
class ContentRevision extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['values' => 'array'];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
