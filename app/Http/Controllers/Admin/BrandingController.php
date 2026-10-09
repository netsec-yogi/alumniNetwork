<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ContentRevision;
use App\Services\Content\Branding;
use App\Services\Uploads\UploadRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Settings → Branding: portal name, logos and favicon, edited as a draft and then published. */
class BrandingController extends Controller
{
    public function __construct(private readonly Branding $branding) {}

    public function edit(): Response
    {
        $this->authorize(Permission::PortalBrandingManage->value);
        $draft = $this->branding->draft();

        return Inertia::render('Admin/Branding/Index', [
            'values' => Arr::only($draft, ['name', 'tagline', 'show_name']),
            'slots' => $this->branding->slotsForEditor(),
            'preview' => Arr::except($this->branding->forPages(draft: true), 'file_ids'),
            'status' => $this->branding->status(),
            'history' => $this->branding->history(),
            'maxKb' => config('security.uploads.max_logo_kb'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize(Permission::PortalBrandingManage->value);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:80'],
            'show_name' => ['required', 'boolean'],
        ]);
        $this->branding->saveDraft([...$data, 'tagline' => $data['tagline'] ?? ''], $request->user());

        return back()->with('success', 'Draft saved. Publish it to show it on the portal.');
    }

    public function uploadLogo(Request $request, string $slot): RedirectResponse
    {
        $this->authorize(Permission::PortalBrandingManage->value);
        abort_unless(isset(Branding::SLOTS[$slot]), 404);
        $request->validate(['logo' => ['required', 'file', 'max:'.config('security.uploads.max_logo_kb')]], [
            'logo.max' => 'The logo must be '.round(config('security.uploads.max_logo_kb') / 1024).' MB or smaller.',
        ]);

        try {
            $this->branding->uploadLogo($slot, $request->file('logo'), $request->user());
        } catch (UploadRejected $e) {
            throw ValidationException::withMessages(['logo' => $e->getMessage()]);
        }

        return back()->with('success', Branding::SLOTS[$slot]['label'].' added to the draft. Preview it, then publish.');
    }

    public function removeLogo(Request $request, string $slot): RedirectResponse
    {
        $this->authorize(Permission::PortalBrandingManage->value);
        $this->branding->removeLogo($slot, $request->user());

        return back()->with('success', Branding::SLOTS[$slot]['label'].' removed from the draft; the default will be used.');
    }

    public function publish(Request $request): RedirectResponse
    {
        $this->authorize(Permission::PortalBrandingManage->value);
        $this->branding->publish($request->user());

        return back()->with('success', 'Branding published. It now shows across the portal.');
    }

    public function unpublish(Request $request): RedirectResponse
    {
        $this->authorize(Permission::PortalBrandingManage->value);
        $this->branding->unpublish($request->user());

        return back()->with('success', 'Custom branding unpublished. The portal shows the default logo and name again.');
    }

    public function restore(Request $request): RedirectResponse
    {
        $this->authorize(Permission::PortalBrandingManage->value);
        $data = $request->validate(['revision' => ['nullable', 'integer', Rule::exists('content_revisions', 'id')->where('area', $this->branding->area())]]);
        $this->branding->restore(isset($data['revision']) ? ContentRevision::find($data['revision']) : null, $request->user());

        return back()->with('success', 'Loaded into the draft. Review it, then publish.');
    }
}
