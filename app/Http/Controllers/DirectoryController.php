<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\AlumniProfile;
use App\Models\DistinguishedAlumnus;
use App\Models\Programme;
use App\Models\Report;
use App\Services\ConnectionService;
use App\Services\ProfileVisibility;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Alumni directory and profile pages (SRS 21-23), privacy-filtered. */
class DirectoryController extends Controller
{
    private const PER_PAGE = 24;

    public function __construct(
        private readonly ProfileVisibility $visibility,
        private readonly ConnectionService $connections,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AlumniProfile::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'programme' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'company' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'interest' => ['nullable', 'string', 'in:'.implode(',', array_keys(AlumniProfile::INTERESTS))],
        ]);

        $viewer = $request->user();
        $like = fn (string $v) => '%'.addcslashes($v, '%_\\').'%';

        $profiles = AlumniProfile::query()
            ->verified()
            ->whereNotIn('user_id', $this->connections->blockedIds($viewer->id))
            ->with(['user:id,name', 'programme:id,name,department_id', 'programme.department:id,name', 'photo'])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('preferred_name', 'like', $like($term))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like($term)))))
            ->when($filters['programme'] ?? null, fn ($q, $id) => $q->where('programme_id', $id))
            ->when($filters['year'] ?? null, fn ($q, $year) => $q->where('graduation_year', $year))
            // Privacy-controlled fields are only searchable where visible.
            ->when($filters['company'] ?? null, fn ($q, $company) => $this->visibility
                ->scopeVisible($q, 'company', $viewer)
                ->where('company', 'like', $like($company)))
            ->when($filters['location'] ?? null, fn ($q, $loc) => $this->visibility
                ->scopeVisible($q, 'location', $viewer)
                ->where(fn ($q) => $q->where('city', 'like', $like($loc))->orWhere('country', 'like', $like($loc))))
            ->when($filters['interest'] ?? null, fn ($q, $interest) => $q->whereJsonContains('interests', $interest))
            ->orderByDesc('graduation_year')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (AlumniProfile $p) => $this->visibility->present($p, $viewer, summary: true));

        return Inertia::render('Directory/Index', [
            'profiles' => $profiles,
            'filters' => (object) $filters,
            'programmes' => Programme::orderBy('name')->get(['id', 'name']),
            'interestOptions' => AlumniProfile::INTERESTS,
        ]);
    }

    public function show(Request $request, AlumniProfile $profile): Response
    {
        $this->authorize('view', $profile);

        $viewer = $request->user();
        $isOwner = $viewer->id === $profile->user_id;

        return Inertia::render('Directory/Show', [
            'profile' => $this->visibility->present($profile, $viewer),
            'interestOptions' => AlumniProfile::INTERESTS,
            'isOwner' => $isOwner,
            'relationship' => $isOwner || ! $viewer->can('interact', $profile) ? null : [
                ...$this->connections->relationship($viewer, $profile->user),
                'mutual' => $this->connections->mutualCount($viewer->id, $profile->user_id),
            ],
            'reportReasons' => Report::REASONS,
            'achievements' => $profile->hasMany(Achievement::class)->published()->orderByDesc('achieved_on')->limit(10)->get()
                ->map(fn ($a) => ['title' => $a->title, 'category' => Achievement::CATEGORIES[$a->category] ?? $a->category, 'date' => $a->achieved_on?->format('M Y'), 'link_url' => $a->link_url]),
            'distinguished' => ($d = DistinguishedAlumnus::where('alumni_profile_id', $profile->id)->where('is_published', true)->first())
                ? ['category' => DistinguishedAlumnus::CATEGORIES[$d->category] ?? $d->category, 'year' => $d->award_year] : null,
        ]);
    }
}
