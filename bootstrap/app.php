<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnforceSessionTimeouts;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottleAuthEndpoints;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware(['web', 'auth', 'verified', 'can:access-admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);

        $middleware->web(append: [
            SecurityHeaders::class,
            ThrottleAuthEndpoints::class,
            EnsureAccountIsActive::class,
            EnforceSessionTimeouts::class,
            EnsureTwoFactorEnrolled::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        // Trust X-Forwarded-* only from the proxies named in TRUSTED_PROXIES
        // (comma-separated IPs/CIDRs). Trusting '*' would let any client spoof
        // its IP and walk around every IP-based rate limit and audit record.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia')),
        );

        // SRS 83: outside local development, errors render a friendly page
        // with the request id -- never a stack trace, query or path.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (app()->environment(['local', 'testing']) || ! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return $response;
            }

            return Inertia::render('Error', [
                'status' => $status,
                'requestId' => $request->attributes->get('request_id'),
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
