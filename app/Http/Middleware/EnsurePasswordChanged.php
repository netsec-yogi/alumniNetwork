<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an administrator SET someone's password (so the administrator knows
 * it), the user must choose their own before doing anything else.
 */
class EnsurePasswordChanged
{
    private const ALLOWED = ['password.change-required', 'user-password.update', 'logout', 'password.confirm', 'password.confirm.store', 'password.confirmation'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->password_change_required && ! $request->routeIs(...self::ALLOWED)) {
            return $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['message' => 'You must choose a new password before continuing.'], 403)
                : redirect()->route('password.change-required');
        }

        return $next($request);
    }
}
