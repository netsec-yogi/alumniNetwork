<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify throttles login and 2FA itself, but registers registration and
 * password-reset routes without any rate limit. This applies the named
 * limiters from FortifyServiceProvider to them (SRS 66).
 */
class ThrottleAuthEndpoints
{
    private const LIMITERS = [
        'register.store' => 'registration',
        'password.email' => 'password-reset',
        'password.update' => 'password-reset',
        'password.confirm.store' => 'sensitive',
    ];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITERS[$request->route()?->getName()] ?? null;

        return $limiter
            ? $this->throttle->handle($request, $next, $limiter)
            : $next($request);
    }
}
