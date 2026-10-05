<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Active devices (SRS 16): list, sign out one, sign out all others. */
class SessionController extends Controller
{
    public function __construct(
        private readonly SessionManager $sessions,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Profile/Sessions', [
            'sessions' => $this->sessions->forUser($request->user(), $request->session()->getId()),
        ]);
    }

    public function destroy(Request $request, string $key): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:web']]);

        if ($this->sessions->revoke($request->user(), $key, $request->session()->getId())) {
            $this->audit->record('session.revoked', 'auth', $request->user());
        }

        return back()->with('success', 'That device has been signed out.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:web']]);

        $count = $this->sessions->revokeAll($request->user(), $request->session()->getId());
        $request->user()->forceFill(['remember_token' => str()->random(60)])->save();

        $this->audit->record('session.revoked_all_others', 'auth', $request->user(), null, ['count' => $count]);

        return back()->with('success', "Signed out of {$count} other ".str('device')->plural($count).'.');
    }
}
