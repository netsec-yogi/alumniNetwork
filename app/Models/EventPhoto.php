<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['caption'])]
/**
 * A photo in an event's gallery. Official photos are managed by event
 * staff (ordered; one may be featured and used wherever the event is shown);
 * the rest are attendee uploads, visible to members only.
 */
class EventPhoto extends Model
{
    protected function casts(): array
    {
        return ['is_official' => 'boolean', 'is_featured' => 'boolean', 'sort_order' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
