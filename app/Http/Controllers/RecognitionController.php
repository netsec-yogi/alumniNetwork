<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\DistinguishedAlumnus;
use App\Models\Programme;
use App\Models\StoredFile;
use App\Models\Story;
use App\Services\PublicMedia;
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
 * Public recognition pages (SRS 39-41, 120): achievements, distinguished
 * alumni and stories. Readable without signing in; only published items
 * are ever shown.
 */
class RecognitionController extends Controller
{
    private function person($profile): array
    {
        return [
            'name' => $profile->displayName(),
            'batch' => "{$profile->programme->code} · {$profile->graduation_year}",
            // Public pages: a photo only if it is public, or the alumnus is publicly featured (PublicMedia).
            'photo_url' => $profile->photo && ($profile->photo->visibility === StoredFile::PUBLIC || app(PublicMedia::class)->isFeatured($profile)) ? $profile->photo->url(true) : null,
            'profile_id' => $profile->id,
        ];
    }

    public function achievements(Request $request): Response
    {
        $category = $request->validate(['category' => ['nullable', Rule::in(array_keys(Achievement::CATEGORIES))]])['category'] ?? null;

        return Inertia::render('Recognition/Achievements', [
            'achievements' => Achievement::published()
                ->when($category, fn ($q) => $q->where('category', $category))
                ->with(['profile.user:id,name', 'profile.programme:id,code', 'profile.photo', 'image'])
                ->orderByDesc('achieved_on')->orderByDesc('id')
                ->paginate(18)->withQueryString()
                ->through(fn (Achievement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'category' => Achievement::CATEGORIES[$a->category] ?? $a->category,
                    'description' => $a->description,
                    'date' => $a->achieved_on?->format('M Y'),
                    'link_url' => $a->link_url,
                    'image_url' => $a->image?->url(),
                    'person' => $this->person($a->profile),
                ]),
            'category' => $category,
            'categories' => collect(Achievement::CATEGORIES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
        ]);
    }

    public function mine(Request $request): Response
    {
        $profile = $request->user()->alumniProfile;
        abort_unless($profile?->isVerified(), 403);

        return Inertia::render('Recognition/MyAchievements', [
            'achievements' => $profile->hasMany(Achievement::class)->latest()->get()->map(fn (Achievement $a) => [
                'id' => $a->id, 'title' => $a->title, 'category' => Achievement::CATEGORIES[$a->category] ?? $a->category,
                'status' => $a->status, 'rejection_reason' => $a->rejection_reason, 'date' => $a->achieved_on?->format('M Y'),
            ]),
            'categories' => collect(Achievement::CATEGORIES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
        ]);
    }

    public function submit(Request $request, FileUploadService $uploads): RedirectResponse
    {
        $profile = $request->user()->alumniProfile;
        abort_unless($profile?->isVerified(), 403);

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Achievement::CATEGORIES))],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'achieved_on' => ['nullable', 'date', 'before_or_equal:today'],
            'link_url' => ['nullable', 'url:https', 'max:500'],
            'image' => ['nullable', 'file', 'max:'.config('security.uploads.max_image_kb')],
        ]);

        $achievement = new Achievement(Arr::except($data, ['image']));
        $achievement->forceFill(['alumni_profile_id' => $profile->id, 'status' => Achievement::SUBMITTED]);

        if ($request->hasFile('image')) {
            try {
                // Private until a reviewer publishes the achievement.
                $file = $uploads->storeImage($request->file('image'), $request->user(), 'achievement_image', StoredFile::PRIVATE, 1200);
            } catch (UploadRejected $e) {
                throw ValidationException::withMessages(['image' => $e->getMessage()]);
            }
            $achievement->image_file_id = $file->id;
        }
        $achievement->save();
        $achievement->image?->attachable()->associate($achievement)->save();

        return back()->with('success', 'Submitted. The alumni office will review it before it’s published.');
    }

    public function withdraw(Request $request, Achievement $achievement): RedirectResponse
    {
        abort_unless($achievement->alumni_profile_id === $request->user()->alumniProfile?->id, 403);
        $achievement->delete();

        return back()->with('success', 'Removed.');
    }

    public function distinguished(): Response
    {
        return Inertia::render('Recognition/Distinguished', [
            'honourees' => DistinguishedAlumnus::where('is_published', true)
                ->with(['profile.user:id,name', 'profile.programme:id,code', 'profile.photo'])
                ->orderByDesc('award_year')->get()
                ->groupBy(fn ($d) => DistinguishedAlumnus::CATEGORIES[$d->category] ?? $d->category)
                ->map(fn ($group) => $group->map(fn (DistinguishedAlumnus $d) => [
                    'id' => $d->id, 'award_year' => $d->award_year, 'citation' => $d->citation,
                    'person' => $this->person($d->profile), 'company' => $d->profile->company, 'designation' => $d->profile->designation,
                ])->values()),
        ]);
    }

    public function stories(Request $request): Response
    {
        $f = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(Story::TYPES))],
            'programme' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer'],
        ]);

        return Inertia::render('Recognition/Stories', [
            'stories' => Story::published()
                ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
                ->when($f['programme'] ?? null, fn ($q, $v) => $q->where('programme_id', $v))
                ->when($f['year'] ?? null, fn ($q, $v) => $q->where('batch_year', $v))
                ->with('cover')->latest('published_at')->paginate(12)->withQueryString()
                ->through(fn (Story $s) => [
                    'slug' => $s->slug, 'title' => $s->title, 'excerpt' => $s->excerpt, 'type' => Story::TYPES[$s->type],
                    'cover_url' => $s->cover?->url(), 'date' => $s->published_at->format('j M Y'),
                ]),
            'filters' => (object) $f,
            'types' => collect(Story::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'programmes' => Programme::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
        ]);
    }

    public function story(Story $story): Response
    {
        abort_unless($story->status === 'published' && $story->published_at?->isPast(), 404);
        $story->load(['cover', 'images.file', 'profile.user:id,name', 'profile.programme:id,code', 'profile.photo', 'programme:id,name', 'author:id,name']);

        return Inertia::render('Recognition/Story', [
            'story' => [
                'title' => $story->title,
                'type' => Story::TYPES[$story->type],
                'excerpt' => $story->excerpt,
                // Sanitised by Story::bodyHtml (raw HTML stripped, unsafe links refused).
                'html' => $story->bodyHtml(),
                // The featured gallery image is the primary image; the older single cover is the fallback.
                'cover_url' => $story->images->firstWhere('is_featured', true)?->file->url() ?? $story->cover?->url(),
                'gallery' => $story->images->map(fn ($i) => ['thumb' => $i->file->url(true), 'full' => $i->file->url(), 'title' => $i->caption ?: $story->title, 'featured' => $i->is_featured])->values(),
                'video_url' => $story->video_url,
                'date' => $story->published_at->format('j F Y'),
                'person' => $story->profile ? $this->person($story->profile) : null,
                'tags' => array_values(array_filter([
                    $story->programme?->name, $story->batch_year ? "Batch of {$story->batch_year}" : null, $story->industry, $story->location,
                ])),
            ],
        ]);
    }
}
