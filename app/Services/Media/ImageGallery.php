<?php

namespace App\Services\Media;

use App\Models\StoredFile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MediaSettings;
use App\Services\Uploads\FileUploadService;
use App\Services\Uploads\UploadRejected;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ordered image galleries with exactly one featured image (events, news).
 * Images go through the optimising upload pipeline at the admin-configured
 * size limit. Rules: the first image becomes featured; featuring one
 * un-features the rest; deleting the featured image promotes the next;
 * replacing keeps position and featured status.
 */
class ImageGallery
{
    public function __construct(
        private readonly FileUploadService $uploads,
        private readonly MediaSettings $media,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  HasMany  $images  e.g. $event->photos() scoped to official, $story->images()
     * @param  array<string, mixed>  $attributes  extra columns for new rows (e.g. is_official)
     * @param  list<UploadedFile>  $files
     */
    public function add(Model $owner, HasMany $images, array $files, User $by, string $purpose, string $limitKey, array $attributes = []): int
    {
        $limit = $this->media->limit($limitKey);
        $stored = [];
        foreach ($files as $i => $upload) {
            try {
                $stored[] = $this->uploads->storeOptimizedImage($upload, $by, $purpose, StoredFile::MEMBERS, $limit, MediaSettings::LABELS[$limitKey]);
            } catch (UploadRejected $e) {
                foreach ($stored as $f) {
                    $f->forceDelete();
                }
                throw ValidationException::withMessages(["images.{$i}" => "{$upload->getClientOriginalName()}: {$e->getMessage()}"]);
            }
        }

        DB::transaction(function () use ($images, $stored, $by, $attributes, $owner) {
            $next = (int) (clone $images)->max('sort_order') + 1;
            $hasFeatured = (clone $images)->where('is_featured', true)->exists();
            foreach ($stored as $k => $file) {
                $row = $images->make();
                $row->forceFill([...$attributes, 'file_id' => $file->id, 'sort_order' => $next + $k, 'is_featured' => ! $hasFeatured && $k === 0])->save();
                $file->attachable()->associate($owner)->save();
            }
            $this->audit->record('media.images_added', 'content', $owner, null, ['count' => count($stored)], $by);
        });

        return count($stored);
    }

    public function feature(Model $owner, HasMany $images, Model $image, User $by): void
    {
        DB::transaction(function () use ($images, $image) {
            (clone $images)->where('is_featured', true)->update(['is_featured' => false]);
            $image->forceFill(['is_featured' => true])->save();
        });
        $this->audit->record('media.image_featured', 'content', $owner, null, ['image' => $image->getKey()], $by);
    }

    /** @param  list<int>  $ids  every image id, in the new order */
    public function reorder(Model $owner, HasMany $images, array $ids, User $by): void
    {
        $own = (clone $images)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $given = collect($ids)->map(fn ($id) => (int) $id);
        if ($given->sort()->values()->all() !== $own) {
            throw ValidationException::withMessages(['order' => 'The order must list each image of this gallery exactly once.']);
        }
        DB::transaction(function () use ($images, $given) {
            foreach ($given as $position => $id) {
                (clone $images)->whereKey($id)->update(['sort_order' => $position]);
            }
        });
        $this->audit->record('media.images_reordered', 'content', $owner, null, null, $by);
    }

    public function replace(Model $owner, Model $image, UploadedFile $upload, User $by, string $purpose, string $limitKey): void
    {
        try {
            $file = $this->uploads->storeOptimizedImage($upload, $by, $purpose, StoredFile::MEMBERS, $this->media->limit($limitKey), MediaSettings::LABELS[$limitKey]);
        } catch (UploadRejected $e) {
            throw ValidationException::withMessages(['image' => $e->getMessage()]);
        }
        $old = StoredFile::find($image->file_id);
        $image->forceFill(['file_id' => $file->id])->save();
        $file->attachable()->associate($owner)->save();
        $old?->forceDelete();
        $this->audit->record('media.image_replaced', 'content', $owner, null, ['image' => $image->getKey()], $by);
    }

    public function delete(Model $owner, HasMany $images, Model $image, User $by): void
    {
        DB::transaction(function () use ($images, $image) {
            $wasFeatured = (bool) $image->is_featured;
            $file = StoredFile::find($image->file_id);
            $image->delete();
            // Removed files are gone for good: no lingering public URL.
            $file?->forceDelete();
            if ($wasFeatured) {
                (clone $images)->orderBy('sort_order')->orderBy('id')->first()?->forceFill(['is_featured' => true])->save();
            }
        });
        $this->audit->record('media.image_deleted', 'content', $owner, null, ['image' => $image->getKey()], $by);
    }
}
