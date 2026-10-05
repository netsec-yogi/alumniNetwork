<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idle and absolute session limits for privileged users (SRS 16, 78).
 *
 * Everyone gets SESSION_LIFETIME as an idle limit from the session driver
 * itself. Holders of administrative roles additionally get a shorter idle
 * limit and an absolute limit counted from sign-in.
 */
class EnforceSessionTimeouts
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isPrivileged()) {
            $session = $request->session();
            $now = now()->getTimestamp();

            $startedAt = $session->get('auth.started_at') ?? tap($now, fn ($t) => $session->put('auth.started_at', $t));
            $lastSeen = $session->get('auth.last_seen_at', $now);

            $idle = ($now - $lastSeen) > config('security.privileged_idle_timeout') * 60;
            $expired = ($now - $startedAt) > config('security.privileged_absolute_timeout') * 60;

            if ($idle || $expired) {
                $this->audit->record('session.expired', 'auth', $user, null, ['reason' => $idle ? 'idle' : 'absolute'], $user);

                Auth::guard('web')->logout();
                $session->invalidate();
                $session->regenerateToken();

                return redirect()->guest(route('login'))->with('status', __('Your session expired for security. Please sign in again.'));
            }

            $session->put('auth.last_seen_at', $now);
        }

        return $next($request);
    }
}
