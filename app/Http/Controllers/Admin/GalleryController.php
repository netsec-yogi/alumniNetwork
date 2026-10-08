<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\GalleryItem;
use App\Models\StoredFile;
use App\Services\AuditLogger;
use App\Services\Uploads\FileUploadService;
use App\Services\Uploads\UploadRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public photo gallery (landing page). A photo's file is public only while
 * the photo is published; drafts and archived photos keep a members-only
 * file, so an unpublished image is not reachable even by URL.
 */
class GalleryController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permission::ContentManage->value), 403);
    }

    public function index(Request $request): Response
    {
        $this->guard($request);
        $status = $request->validate(['status' => ['nullable', Rule::in([GalleryItem::DRAFT, GalleryItem::PUBLISHED, GalleryItem::ARCHIVED])]])['status'] ?? null;

        return Inertia::render('Admin/Gallery/Index', [
            'status' => $status,
            'items' => GalleryItem::with(['file', 'event:id,title'])
                ->when($status, fn ($q, $s) => $q->where('status', $s))
                ->orderByDesc('is_featured')->orderBy('display_order')->latest()
                ->paginate(24)->withQueryString()
                ->through(fn (GalleryItem $g) => [
                    ...$g->only(['id', 'title', 'caption', 'category', 'status', 'is_featured', 'display_order', 'event_id']),
                    'visible_from' => $g->visible_from?->toDateString(),
                    'visible_until' => $g->visible_until?->toDateString(),
                    'thumb' => $g->file->url(true),
                    'event' => $g->event?->title,
                ]),
            'categories' => collect(GalleryItem::CATEGORIES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'events' => Event::published()->orderByDesc('starts_at')->limit(50)->get(['id', 'title'])->map(fn ($e) => ['value' => $e->id, 'label' => $e->title]),
        ]);
    }

    public function store(Request $request, FileUploadService $uploads): RedirectResponse
    {
        $this->guard($request);
        $data = $this->validated($request, creating: true);

        try {
            $file = $uploads->storeImage($request->file('photo'), $request->user(), 'gallery', StoredFile::MEMBERS, 2000, 640);
        } catch (UploadRejected $e) {
            throw ValidationException::withMessages(['photo' => $e->getMessage()]);
        }

        $item = new GalleryItem(Arr::except($data, ['photo', 'status']));
        $item->forceFill(['stored_file_id' => $file->id, 'created_by' => $request->user()->id, 'status' => $data['status']])->save();
        $this->syncFileVisibility($item);
        $this->audit->record('gallery.created', 'content', $item, null, ['status' => $item->status]);

        return back()->with('success', 'Photo added.');
    }

    public function update(Request $request, GalleryItem $item): RedirectResponse
    {
        $this->guard($request);
        $data = $this->validated($request);
        $original = $item->getAttributes();

        $item->fill(Arr::except($data, ['status']))->forceFill(['status' => $data['status']])->save();
        $this->syncFileVisibility($item);
        $this->audit->recordChanges('gallery.updated', 'content', $item, $original);

        return back()->with('success', 'Photo updated.');
    }

    public function destroy(Request $request, GalleryItem $item): RedirectResponse
    {
        $this->guard($request);
        $file = $item->file;
        $item->delete();
        $file?->delete();
        $this->audit->record('gallery.deleted', 'content', null, ['title' => $item->title], null);

        return back()->with('success', 'Photo deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating = false): array
    {
        $data = $request->validate([
            'photo' => [$creating ? 'required' : 'prohibited', 'file', 'max:'.config('security.uploads.max_image_kb')],
            'title' => ['required', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:300'],
            'category' => ['required', Rule::in(array_keys(GalleryItem::CATEGORIES))],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'status' => ['required', Rule::in([GalleryItem::DRAFT, GalleryItem::PUBLISHED, GalleryItem::ARCHIVED])],
            'is_featured' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
        ]);
        $data['display_order'] = (int) ($data['display_order'] ?? 0);

        return $data;
    }

    /** Public file only while published. */
    private function syncFileVisibility(GalleryItem $item): void
    {
        $item->file->forceFill(['visibility' => $item->status === GalleryItem::PUBLISHED ? StoredFile::PUBLIC : StoredFile::MEMBERS])->save();
    }
}
