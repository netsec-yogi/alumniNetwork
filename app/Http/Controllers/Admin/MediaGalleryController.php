<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Story;
use App\Models\StoryImage;
use App\Services\Media\ImageGallery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Image galleries for events (event staff, per the event policy) and for
 * news/stories (content managers). Every action checks that the image
 * belongs to the event/story in the URL (no cross-gallery tampering).
 */
class MediaGalleryController extends Controller
{
    public function __construct(private readonly ImageGallery $gallery) {}

    /** @return array{0: Model, 1: HasMany, 2: string, 3: string, 4: array<string, mixed>} owner, images, purpose, limit key, row attributes */
    private function resolve(Request $request, string $type, int $id): array
    {
        if ($type === 'events') {
            $event = Event::findOrFail($id);
            $this->authorize('update', $event);

            return [$event, $event->photos()->where('is_official', true), 'event_image', 'event_image_kb', ['is_official' => true, 'uploaded_by' => $request->user()->id]];
        }
        abort_unless($request->user()->can(Permission::ContentManage->value), 403);
        $story = Story::findOrFail($id);

        return [$story, $story->images(), 'news_image', 'news_image_kb', []];
    }

    private function image(HasMany $images, int $imageId): EventPhoto|StoryImage
    {
        return (clone $images)->whereKey($imageId)->firstOrFail();
    }

    public function store(Request $request, string $type, int $id): RedirectResponse
    {
        [$owner, $images, $purpose, $limit, $attrs] = $this->resolve($request, $type, $id);
        $request->validate(['images' => ['required', 'array', 'min:1', 'max:10'], 'images.*' => ['file', 'max:'.config('security.uploads.max_image_kb')]]);

        $n = $this->gallery->add($owner, $images, $request->file('images'), $request->user(), $purpose, $limit, $attrs);

        return back()->with('success', $n === 1 ? 'Image added.' : "{$n} images added.");
    }

    public function feature(Request $request, string $type, int $id, int $image): RedirectResponse
    {
        [$owner, $images] = $this->resolve($request, $type, $id);
        $this->gallery->feature($owner, $images, $this->image($images, $image), $request->user());

        return back()->with('success', 'Featured image updated.');
    }

    public function reorder(Request $request, string $type, int $id): RedirectResponse
    {
        [$owner, $images] = $this->resolve($request, $type, $id);
        $data = $request->validate(['order' => ['required', 'array', 'max:200'], 'order.*' => ['integer']]);
        $this->gallery->reorder($owner, $images, $data['order'], $request->user());

        return back();
    }

    public function replace(Request $request, string $type, int $id, int $image): RedirectResponse
    {
        [$owner, $images, $purpose, $limit] = $this->resolve($request, $type, $id);
        $request->validate(['image' => ['required', 'file', 'max:'.config('security.uploads.max_image_kb')]]);
        $this->gallery->replace($owner, $this->image($images, $image), $request->file('image'), $request->user(), $purpose, $limit);

        return back()->with('success', 'Image replaced.');
    }

    public function destroy(Request $request, string $type, int $id, int $image): RedirectResponse
    {
        [$owner, $images] = $this->resolve($request, $type, $id);
        $this->gallery->delete($owner, $images, $this->image($images, $image), $request->user());

        return back()->with('success', 'Image deleted.');
    }
}
