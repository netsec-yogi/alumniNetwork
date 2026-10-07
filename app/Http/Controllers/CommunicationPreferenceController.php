<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Consent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Communication consent (SRS 110): profile toggle and signed unsubscribe links. */
class CommunicationPreferenceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function current(User $user): bool
    {
        return (bool) $user->consents()->where('consent_type', Consent::COMMUNICATIONS)->latest('id')->value('granted');
    }

    public function update(Request $request): RedirectResponse
    {
        $granted = $request->validate(['granted' => ['required', 'boolean']])['granted'];
        $this->record($request->user(), (bool) $granted, $request);

        return back()->with('success', $granted ? 'You’ll receive alumni news by email.' : 'You won’t receive alumni news by email.');
    }

    /** GET only shows a confirmation; mail scanners prefetch links. */
    public function show(Request $request, User $user): Response
    {
        return Inertia::render('Unsubscribe', [
            'action' => $request->fullUrl(),
            'subscribed' => self::current($user),
        ]);
    }

    /** Signed POST (also used by RFC 8058 one-click from mail clients). */
    public function unsubscribe(Request $request, User $user): Response
    {
        if (self::current($user)) {
            $this->record($user, false, $request);
        }

        return Inertia::render('Unsubscribe', ['action' => null, 'subscribed' => false]);
    }

    private function record(User $user, bool $granted, Request $request): void
    {
        $user->consents()->create([
            'consent_type' => Consent::COMMUNICATIONS,
            'version' => CreateNewUser::TERMS_VERSION,
            'granted' => $granted,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
        $this->audit->record($granted ? 'consent.communications_granted' : 'consent.communications_withdrawn', 'privacy', $user, null, null, $user);
    }
}
