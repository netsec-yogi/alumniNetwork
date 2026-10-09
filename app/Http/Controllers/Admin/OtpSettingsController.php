<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Auth\OtpLoginSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Settings → OTP sign-in: code length, validity, attempts, cooldown and request cap. */
class OtpSettingsController extends Controller
{
    public function __construct(private readonly OtpLoginSettings $settings, private readonly AuditLogger $audit) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permission::SecurityManage->value), 403);
    }

    public function edit(Request $request): Response
    {
        $this->guard($request);

        return Inertia::render('Admin/Settings/Otp', [
            'values' => $this->settings->all(),
            'fields' => collect(OtpLoginSettings::FIELDS)->map(fn ($f) => ['default' => $f[0], 'min' => $f[1], 'max' => $f[2], 'label' => $f[3], 'unit' => $f[4]]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(collect(OtpLoginSettings::FIELDS)->map(fn ($f) => ['required', 'integer', "between:{$f[1]},{$f[2]}"])->all());

        $before = $this->settings->all();
        $this->settings->save($data);
        $this->audit->record('settings.otp_updated', 'auth', null, $before, $this->settings->all());

        return back()->with('success', 'OTP sign-in settings saved.');
    }
}
