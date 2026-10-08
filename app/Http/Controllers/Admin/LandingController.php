<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\DistinguishedAlumnus;
use App\Models\Event;
use App\Models\GalleryItem;
use App\Models\Story;
use App\Services\AuditLogger;
use App\Services\LandingPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Public landing page layout: section order and visibility, which statistics show. */
class LandingController extends Controller
{
    public function __construct(private readonly LandingPageService $landing, private readonly AuditLogger $audit) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permission::ContentManage->value), 403);
    }

    public function index(Request $request): Response
    {
        $this->guard($request);

        return Inertia::render('Admin/Landing/Index', [
            'settings' => $this->landing->settings(),
            'statLabels' => LandingPageService::STATS,
            // What each section would currently draw from, so empty sections are obvious.
            'content' => [
                'events' => Event::published()->upcoming()->where('audience', Event::AUDIENCE_PUBLIC)->count(),
                'news' => Story::published()->whereIn('type', Story::NEWS_TYPES)->count(),
                'stories' => Story::published()->whereNotIn('type', Story::NEWS_TYPES)->count(),
                'distinguished' => DistinguishedAlumnus::where('is_published', true)->count(),
                'gallery' => GalleryItem::publiclyVisible()->count(),
                'chapters' => Community::where('kind', Community::KIND_CHAPTER)->where('show_on_landing', true)->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.key' => ['required', Rule::in(array_keys(LandingPageService::SECTIONS))],
            'sections.*.visible' => ['required', 'boolean'],
            'stats' => ['required', 'array'],
            'stats.*' => ['boolean'],
        ]);

        $this->landing->saveSettings($data['sections'], $data['stats']);
        $this->audit->record('landing.settings_updated', 'content', null, null, [
            'hidden' => collect($data['sections'])->where('visible', false)->pluck('key')->values()->all(),
        ]);

        return back()->with('success', 'Landing page updated.');
    }
}
