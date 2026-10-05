<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mandatory 2FA (SRS 11). A user whose role requires 2FA but who has not
 * confirmed an authenticator can reach only the security settings page (to
 * enrol) and the routes needed to do so, plus sign-out.
 */
class EnsureTwoFactorEnrolled
{
    /** Route names that must stay reachable to complete enrolment. */
    private const ALLOWED = [
        'profile.security', 'logout', 'password.confirm', 'password.confirm.store', 'password.confirmation',
        'two-factor.enable', 'two-factor.confirm', 'two-factor.disable', 'two-factor.qr-code',
        'two-factor.secret-key', 'two-factor.recovery-codes', 'two-factor.regenerate-recovery-codes',
        'verification.notice', 'verification.verify', 'verification.send', 'user-password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->two_factor_confirmed_at === null
            && $user->requiresTwoFactor()
            && ! $request->routeIs(...self::ALLOWED)) {
            return $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['message' => 'Two-factor authentication must be enabled for your role.'], 403)
                : redirect()->route('profile.security')->with('warning', __('Your role requires two-factor authentication. Please set it up to continue.'));
        }

        return $next($request);
    }
}
