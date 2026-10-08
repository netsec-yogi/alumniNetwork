<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An image in a news item's / story's gallery. One may be featured. */
#[Fillable(['caption'])]
class StoryImage extends Model
{
    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'sort_order' => 'integer'];
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }
}
