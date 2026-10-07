<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\AlumniProfile;
use App\Models\DistinguishedAlumnus;
use App\Models\Programme;
use App\Models\StoredFile;
use App\Models\Story;
use App\Notifications\AchievementReviewed;
use App\Services\AuditLogger;
use App\Services\Uploads\FileUploadService;
use App\Services\Uploads\UploadRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Content management for achievements, distinguished alumni and stories (SRS 39-41). */
class RecognitionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permission::ContentManage->value), 403);
    }

    public function achievements(Request $request): Response
    {
        $this->guard($request);
        $status = $request->validate(['status' => ['nullable', Rule::in(['submitted', 'published', 'rejected'])]])['status'] ?? 'submitted';

        return Inertia::render('Admin/Recognition/Achievements', [
            'status' => $status,
            'achievements' => Achievement::where('status', $status)
                ->with(['profile.user:id,name,email', 'profile.programme:id,code', 'image'])
                ->when($status === 'submitted', fn ($q) => $q->oldest(), fn ($q) => $q->latest('reviewed_at'))
                ->paginate(20)->withQueryString()
                ->through(fn (Achievement $a) => [
                    'id' => $a->id, 'title' => $a->title, 'category' => Achievement::CATEGORIES[$a->category] ?? $a->category,
                    'description' => $a->description, 'date' => $a->achieved_on?->format('j M Y'), 'link_url' => $a->link_url,
                    'image_url' => $a->image?->url(), 'rejection_reason' => $a->rejection_reason,
                    'person' => ['name' => $a->profile->user->name, 'batch' => "{$a->profile->programme->code} · {$a->profile->graduation_year}", 'id' => $a->profile->id],
                    'submitted' => $a->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function review(Request $request, Achievement $achievement): RedirectResponse
    {
        $this->guard($request);
        abort_unless($achievement->status === Achievement::SUBMITTED, 409);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['publish', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
        ]);

        $achievement->forceFill([
            'status' => $data['decision'] === 'publish' ? Achievement::PUBLISHED : Achievement::REJECTED,
            'rejection_reason' => $data['decision'] === 'reject' ? $data['reason'] : null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        if ($achievement->status === Achievement::PUBLISHED) {
            $achievement->image?->forceFill(['visibility' => StoredFile::PUBLIC])->save();
        }

        $this->audit->record("achievement.{$achievement->status}", 'content', $achievement);
        $achievement->profile->user->notify(new AchievementReviewed($achievement));

        return back()->with('success', $achievement->status === Achievement::PUBLISHED ? 'Published.' : 'Rejected; they’ve been told why.');
    }

    public function distinguished(Request $request): Response
    {
        $this->guard($request);

        return Inertia::render('Admin/Recognition/Distinguished', [
            'honourees' => DistinguishedAlumnus::with(['profile.user:id,name', 'profile.programme:id,code'])->orderByDesc('award_year')->get()
                ->map(fn (DistinguishedAlumnus $d) => [
                    ...$d->only(['id', 'category', 'award_year', 'citation', 'is_published']),
                    'name' => $d->profile->user->name, 'batch' => "{$d->profile->programme->code} · {$d->profile->graduation_year}",
                ]),
            'categories' => collect(DistinguishedAlumnus::CATEGORIES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
        ]);
    }

    public function saveDistinguished(Request $request, ?DistinguishedAlumnus $honouree = null): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'roll_number' => [$honouree ? 'nullable' : 'required', 'string', 'max:30'],
            'category' => ['required', Rule::in(array_keys(DistinguishedAlumnus::CATEGORIES))],
            'award_year' => ['required', 'integer', 'min:1998', 'max:'.(now()->year + 1)],
            'citation' => ['required', 'string', 'min:20', 'max:3000'],
            'is_published' => ['boolean'],
        ]);

        if (! $honouree) {
            $profile = AlumniProfile::verified()->where('roll_number', Str::upper(trim($data['roll_number'])))->first();
            if (! $profile) {
                throw ValidationException::withMessages(['roll_number' => 'No verified alumnus with that roll number.']);
            }
            $data['alumni_profile_id'] = $profile->id;
        }
        unset($data['roll_number']);

        $honouree ? $honouree->update($data) : $honouree = DistinguishedAlumnus::updateOrCreate(['alumni_profile_id' => $data['alumni_profile_id']], $data);
        $this->audit->record('distinguished.saved', 'content', $honouree);

        return back()->with('success', 'Saved.');
    }

    public function destroyDistinguished(Request $request, DistinguishedAlumnus $honouree): RedirectResponse
    {
        $this->guard($request);
        $honouree->delete();

        return back()->with('success', 'Removed.');
    }

    public function stories(Request $request): Response
    {
        $this->guard($request);

        return Inertia::render('Admin/Recognition/Stories', [
            'stories' => Story::with('author:id,name')->latest()->paginate(25)->through(fn (Story $s) => [
                'id' => $s->id, 'slug' => $s->slug, 'title' => $s->title, 'type' => Story::TYPES[$s->type], 'status' => $s->status,
                'published_at' => $s->published_at?->format('j M Y'), 'author' => $s->author?->name,
            ]),
        ]);
    }

    public function editStory(Request $request, ?Story $story = null): Response
    {
        $this->guard($request);

        return Inertia::render('Admin/Recognition/StoryForm', [
            'story' => $story ? [
                ...$story->only(['id', 'slug', 'type', 'title', 'excerpt', 'body', 'video_url', 'batch_year', 'programme_id', 'industry', 'location', 'status']),
                'roll_number' => $story->profile?->roll_number,
                'cover_url' => $story->cover?->url(),
            ] : null,
            'types' => collect(Story::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'programmes' => Programme::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
        ]);
    }

    public function saveStory(Request $request, FileUploadService $uploads, ?Story $story = null): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Story::TYPES))],
            'title' => ['required', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string', 'max:100000'],
            'video_url' => ['nullable', 'url:https', 'max:500'],
            'roll_number' => ['nullable', 'string', 'max:30'],
            'batch_year' => ['nullable', 'integer', 'min:1998', 'max:2100'],
            'programme_id' => ['nullable', 'exists:programmes,id'],
            'industry' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'publish' => ['boolean'],
            'cover' => ['nullable', 'file', 'max:'.config('security.uploads.max_image_kb')],
        ]);

        $data['alumni_profile_id'] = ! empty($data['roll_number'])
            ? (AlumniProfile::where('roll_number', Str::upper(trim($data['roll_number'])))->value('id') ?? throw ValidationException::withMessages(['roll_number' => 'No alumnus with that roll number.']))
            : null;

        $story ??= (new Story)->forceFill(['author_id' => $request->user()->id, 'status' => 'draft']);
        $story->fill(Arr::except($data, ['roll_number', 'publish', 'cover']))->forceFill(['alumni_profile_id' => $data['alumni_profile_id']]);

        if ($request->hasFile('cover')) {
            try {
                $story->cover_file_id = $uploads->storeImage($request->file('cover'), $request->user(), 'story_cover', StoredFile::PUBLIC, 1600, 480)->id;
            } catch (UploadRejected $e) {
                throw ValidationException::withMessages(['cover' => $e->getMessage()]);
            }
        }

        if (array_key_exists('publish', $data)) {
            $story->forceFill($data['publish']
                ? ['status' => 'published', 'published_at' => $story->published_at ?? now()]
                : ['status' => 'draft']);
        }
        $story->save();
        $this->audit->record('story.saved', 'content', $story, null, ['status' => $story->status]);

        return redirect()->route('admin.stories.edit', $story)->with('success', $story->status === 'published' ? 'Published.' : 'Draft saved.');
    }

    public function destroyStory(Request $request, Story $story): RedirectResponse
    {
        $this->guard($request);
        $story->delete();
        $this->audit->record('story.deleted', 'content', $story);

        return redirect()->route('admin.stories.index')->with('success', 'Story deleted.');
    }
}
