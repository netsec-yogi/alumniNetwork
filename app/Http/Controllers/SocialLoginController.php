<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\SocialLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use InvalidArgumentException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Sign in with, and link, Google / LinkedIn accounts (SRS 10). Rules live in SocialLoginService. */
class SocialLoginController extends Controller
{
    private const LINK_INTENT = 'social.link_user_id';

    public function __construct(private readonly SocialLoginService $social, private readonly AuditLogger $audit) {}

    private function label(string $provider): string
    {
        return SocialLoginService::enabled()[$provider] ?? abort(404);
    }

    /** Guest: start signing in. */
    public function redirect(Request $request, string $provider): Response
    {
        $this->label($provider);
        $request->session()->forget(self::LINK_INTENT);

        return Socialite::driver($provider)->redirect();
    }

    /** Signed in, password freshly confirmed: start linking. */
    public function link(Request $request, string $provider): Response
    {
        $this->label($provider);
        $request->session()->put(self::LINK_INTENT, $request->user()->id);

        return Inertia::location(Socialite::driver($provider)->redirect()->getTargetUrl());
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $label = $this->label($provider);
        $linkFor = $request->session()->pull(self::LINK_INTENT);

        try {
            // Socialite checks the OAuth `state` against this session, so forged callbacks fail here.
            $identity = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route($linkFor ? 'profile.security' : 'login')->with('error', "Signing in with {$label} didn’t complete. Please try again.");
        }

        if ($linkFor) {
            abort_unless($request->user()?->id === $linkFor, 403);
            try {
                $this->social->link($request->user(), $provider, $identity);
            } catch (InvalidArgumentException $e) {
                return redirect()->route('profile.security')->with('error', $e->getMessage());
            }

            return redirect()->route('profile.security')->with('success', "{$label} is now linked to your account.");
        }

        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        $user = $this->social->findUser($provider, $identity);
        if ($user === null) {
            return redirect()->route('login')->with('warning', "That {$label} account isn’t linked to a member yet. Sign in with your password and link it under Security, or create an account first.");
        }
        if ($user->isLocked() || ! $user->isActive()) {
            $this->audit->record('login.blocked_social', 'auth', $user, null, ['provider' => $provider], $user);

            return redirect()->route('login')->with('error', 'This account can’t be signed in to right now. Please contact the alumni office.');
        }

        // Two-factor still applies: hand over to Fortify's challenge exactly as a password sign-in would.
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function unlink(Request $request, string $provider): RedirectResponse
    {
        // Every account has a password, so unlinking never locks anyone out.
        $this->social->unlink($request->user(), $provider);

        return back()->with('success', 'Unlinked.');
    }
}
