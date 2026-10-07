<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\AlumniProfile;
use App\Models\Startup;
use App\Models\StoredFile;
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

/** Alumni startup directory (SRS 34). */
class StartupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isCommunityMember(), 403);
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'stage' => ['nullable', Rule::in(array_keys(Startup::STAGES))],
            'hiring' => ['nullable', 'boolean'],
        ]);
        $like = fn ($v) => '%'.addcslashes($v, '%_\\').'%';

        return Inertia::render('Startups/Index', [
            'startups' => Startup::where('is_hidden', false)
                ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', $like($t))->orWhere('industry', 'like', $like($t))->orWhere('tagline', 'like', $like($t))))
                ->when($f['stage'] ?? null, fn ($q, $v) => $q->where('funding_stage', $v))
                ->when(! empty($f['hiring']), fn ($q) => $q->where('is_hiring', true))
                ->with(['logo', 'founders.user:id,name', 'founders.programme:id,code'])
                ->orderByDesc('is_hiring')->latest()
                ->paginate(18)->withQueryString()
                ->through(fn (Startup $s) => $this->card($s)),
            'filters' => (object) $f,
            'stages' => $this->stages(),
            'canCreate' => (bool) $request->user()->alumniProfile?->isVerified(),
        ]);
    }

    public function show(Request $request, Startup $startup): Response
    {
        abort_unless($request->user()->isCommunityMember(), 403);
        abort_if($startup->is_hidden && ! $this->canManage($request, $startup), 404);
        $startup->load(['logo', 'founders.user:id,name', 'founders.programme:id,code', 'founders.photo']);

        return Inertia::render('Startups/Show', [
            'startup' => [
                ...$this->card($startup),
                'description' => $startup->description,
                'website_url' => $startup->website_url,
                'founded_year' => $startup->founded_year,
                'founders' => $startup->founders->map(fn (AlumniProfile $p) => [
                    'name' => $p->displayName(), 'role' => $p->pivot->role, 'profile_id' => $p->id,
                    'batch' => "{$p->programme->code} · {$p->graduation_year}", 'photo_url' => $p->photo?->url(true),
                ]),
                'is_hidden' => $startup->is_hidden,
            ],
            'canEdit' => $this->canManage($request, $startup),
            'canModerate' => $request->user()->can(Permission::ContentManage->value),
        ]);
    }

    public function form(Request $request, ?Startup $startup = null): Response
    {
        $startup ? abort_unless($this->canManage($request, $startup), 403) : abort_unless($request->user()->alumniProfile?->isVerified(), 403);

        return Inertia::render('Startups/Form', [
            'startup' => $startup ? [
                ...$startup->only(['slug', 'name', 'tagline', 'description', 'industry', 'website_url', 'location', 'founded_year', 'funding_stage', 'is_hiring']),
                'cofounders' => $startup->load('founders')->founders->where('user_id', '!=', $request->user()->id)->pluck('roll_number')->implode(', '),
                'my_role' => $startup->founders->firstWhere('user_id', $request->user()->id)?->pivot->role,
            ] : null,
            'stages' => $this->stages(),
        ]);
    }

    public function save(Request $request, FileUploadService $uploads, AuditLogger $audit, ?Startup $startup = null): RedirectResponse
    {
        $me = $request->user()->alumniProfile;
        $startup ? abort_unless($this->canManage($request, $startup), 403) : abort_unless($me?->isVerified(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'industry' => ['required', 'string', 'max:100'],
            'website_url' => ['nullable', 'url:https', 'max:255'],
            'location' => ['nullable', 'string', 'max:120'],
            'founded_year' => ['nullable', 'integer', 'min:1990', 'max:'.now()->year],
            'funding_stage' => ['required', Rule::in(array_keys(Startup::STAGES))],
            'is_hiring' => ['boolean'],
            'my_role' => ['nullable', 'string', 'max:80'],
            'cofounders' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'file', 'max:'.config('security.uploads.max_image_kb')],
        ]);

        // Co-founders by roll number; only verified alumni can be listed.
        $rolls = collect(preg_split('/[\s,]+/', (string) ($data['cofounders'] ?? '')))->filter()->map(fn ($r) => Str::upper($r))->unique();
        $cofounders = AlumniProfile::verified()->whereIn('roll_number', $rolls)->get();
        if ($cofounders->count() !== $rolls->count()) {
            throw ValidationException::withMessages(['cofounders' => 'Unknown or unverified roll numbers: '.$rolls->diff($cofounders->pluck('roll_number'))->implode(', ')]);
        }

        $startup ??= (new Startup)->forceFill(['created_by' => $request->user()->id]);
        $startup->fill(Arr::except($data, ['my_role', 'cofounders', 'logo']));

        if ($request->hasFile('logo')) {
            try {
                $startup->logo_file_id = $uploads->storeImage($request->file('logo'), $request->user(), 'startup_logo', StoredFile::MEMBERS, 400, 160)->id;
            } catch (UploadRejected $e) {
                throw ValidationException::withMessages(['logo' => $e->getMessage()]);
            }
        }
        $startup->save();

        // Founders manage the founder list; a content manager editing someone
        // else's startup leaves it untouched (and is never added to it).
        if (! $startup->wasRecentlyCreated && ! $startup->isFounder($request->user())) {
            $audit->record('startup.saved', 'network', $startup);

            return redirect()->route('startups.show', $startup)->with('success', 'Saved.');
        }

        $founders = $cofounders->mapWithKeys(fn ($p) => [$p->id => ['role' => $startup->founders()->whereKey($p->id)->first()?->pivot->role]]);
        $founders[$me->id] = ['role' => $data['my_role'] ?? null];
        $startup->founders()->sync($founders->all());
        $audit->record('startup.saved', 'network', $startup);

        return redirect()->route('startups.show', $startup)->with('success', 'Saved.');
    }

    public function toggleHidden(Request $request, Startup $startup, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::ContentManage->value), 403);
        $startup->forceFill(['is_hidden' => ! $startup->is_hidden])->save();
        $audit->record($startup->is_hidden ? 'startup.hidden' : 'startup.shown', 'network', $startup);

        return back()->with('success', $startup->is_hidden ? 'Hidden from the directory.' : 'Visible again.');
    }

    private function canManage(Request $request, Startup $startup): bool
    {
        return $startup->isFounder($request->user()) || $request->user()->can(Permission::ContentManage->value);
    }

    private function card(Startup $s): array
    {
        return [
            'slug' => $s->slug, 'name' => $s->name, 'tagline' => $s->tagline, 'industry' => $s->industry, 'location' => $s->location,
            'stage' => Startup::STAGES[$s->funding_stage] ?? $s->funding_stage, 'is_hiring' => $s->is_hiring, 'logo_url' => $s->logo?->url(true),
            'founder_names' => $s->founders->map(fn ($p) => $p->user->name)->implode(', '),
        ];
    }

    private function stages(): array
    {
        return collect(Startup::STAGES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all();
    }
}
