<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlation id for every request (SRS 81). Accepts a well-formed upstream
 * X-Request-ID (e.g. from the reverse proxy) so one id spans the whole hop,
 * otherwise generates one. It is attached to every log line and audit entry
 * and returned to the client for support tickets.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-ID');
        $id = preg_match('/^[A-Za-z0-9\-]{8,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();

        $request->attributes->set('request_id', $id);
        Log::shareContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $id);

        return $response;
    }
}
