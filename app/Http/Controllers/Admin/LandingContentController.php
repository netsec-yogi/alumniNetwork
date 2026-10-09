<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LandingController as PublicLanding;
use App\Models\ContentRevision;
use App\Services\Content\Branding;
use App\Services\Content\LandingCopy;
use App\Services\LandingPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Content → Landing page text: titles, descriptions and button text, as a draft, previewed, then published. */
class LandingContentController extends Controller
{
    public function __construct(private readonly LandingCopy $copy) {}

    public function edit(): Response
    {
        $this->authorize(Permission::LandingContentManage->value);

        return Inertia::render('Admin/Landing/Content', [
            'sections' => LandingCopy::SECTIONS,
            'fields' => collect(LandingCopy::fields())->map(fn ($f, $key) => ['key' => $key, ...Arr::only($f, ['section', 'label', 'type', 'default', 'required', 'max', 'hint'])])->values(),
            'values' => $this->copy->draft(),
            'status' => $this->copy->status(),
            'history' => $this->copy->history(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize(Permission::LandingContentManage->value);
        $rules = collect(LandingCopy::fields())->mapWithKeys(fn ($f, $key) => [
            'values.'.str_replace('.', '\.', $key) => [$f['required'] ? 'required' : 'nullable', 'string', 'max:'.$f['max']],
        ])->all();
        $data = $request->validate(['values' => ['required', 'array'], ...$rules], [
            'values.*.required' => 'This text is required. Use “Reset to default” to bring back the original.',
        ]);
        $values = Arr::only($data['values'], array_keys(LandingCopy::fields()));
        $this->copy->saveDraft(array_map(fn ($v) => $v ?? '', $values), $request->user());

        return back()->with('success', 'Draft saved. Preview it, then publish.');
    }

    /** The real landing page, rendered from the drafts (text and branding). Never cached, never public. */
    public function preview(LandingPageService $landing, Branding $branding): Response
    {
        abort_unless(request()->user()->canAny([Permission::LandingContentManage->value, Permission::PortalBrandingManage->value]), 403);
        Inertia::share('branding', Arr::except($branding->forPages(draft: true), 'file_ids'));

        return Inertia::render('Welcome', [...PublicLanding::props($landing->previewPayload()), 'preview' => true]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $this->authorize(Permission::LandingContentManage->value);
        $this->copy->publish($request->user());

        return back()->with('success', 'Published. The landing page now shows this text.');
    }

    public function unpublish(Request $request): RedirectResponse
    {
        $this->authorize(Permission::LandingContentManage->value);
        $this->copy->unpublish($request->user());

        return back()->with('success', 'Unpublished. The landing page shows the default text again.');
    }

    public function restore(Request $request): RedirectResponse
    {
        $this->authorize(Permission::LandingContentManage->value);
        $data = $request->validate(['revision' => ['nullable', 'integer', Rule::exists('content_revisions', 'id')->where('area', $this->copy->area())]]);
        $this->copy->restore(isset($data['revision']) ? ContentRevision::find($data['revision']) : null, $request->user());

        return back()->with('success', 'Loaded into the draft. Review it, then publish.');
    }
}
