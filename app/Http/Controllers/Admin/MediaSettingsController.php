<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\MediaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Settings → Media: maximum stored size of event, news and profile images. */
class MediaSettingsController extends Controller
{
    public function __construct(private readonly MediaSettings $media, private readonly AuditLogger $audit) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permission::ContentManage->value), 403);
    }

    public function edit(Request $request): Response
    {
        $this->guard($request);

        return Inertia::render('Admin/Settings/Media', [
            'values' => $this->media->all(),
            'labels' => MediaSettings::LABELS,
            'defaults' => MediaSettings::DEFAULTS,
            'bounds' => ['min' => MediaSettings::MIN_KB, 'max' => MediaSettings::MAX_KB],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->guard($request);
        $rule = ['required', 'integer', 'min:'.MediaSettings::MIN_KB, 'max:'.MediaSettings::MAX_KB];
        $data = $request->validate(collect(MediaSettings::DEFAULTS)->map(fn () => $rule)->all(), [
            '*.min' => 'Use at least '.MediaSettings::MIN_KB.' KB — smaller limits make most photos impossible to save.',
            '*.max' => 'Use at most '.MediaSettings::MAX_KB.' KB.',
        ]);

        $before = $this->media->all();
        $this->media->save($data);
        $this->audit->record('settings.media_updated', 'content', null, $before, $this->media->all());

        return back()->with('success', 'Media settings saved.');
    }
}
