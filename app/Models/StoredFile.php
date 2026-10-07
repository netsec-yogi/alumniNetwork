<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** An uploaded file. Created only by FileUploadService; served only by FileController. */
class StoredFile extends Model
{
    use HasUlids, SoftDeletes;

    public const PUBLIC = 'public';

    public const MEMBERS = 'members';

    public const PRIVATE = 'private';

    protected $hidden = ['path', 'thumb_path', 'sha256'];

    protected static function booted(): void
    {
        // Remove the bytes when the record is permanently deleted.
        static::forceDeleted(function (StoredFile $file) {
            Storage::disk('local')->delete(array_filter([$file->path, $file->thumb_path]));
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isImage(): bool
    {
        return $this->kind === 'image';
    }

    public function url(bool $thumb = false): string
    {
        return route('files.show', ['file' => $this->id, 'variant' => $thumb && $this->thumb_path ? 'thumb' : null], false);
    }
}
