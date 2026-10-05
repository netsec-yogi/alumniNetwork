<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP security headers and Content Security Policy (SRS 62-64).
 *
 * Scripts and styles are nonce-based: Vite tags carry the per-request nonce
 * and no inline script is ever emitted, so neither 'unsafe-inline' nor
 * 'unsafe-eval' is needed. Set CSP_REPORT_ONLY=true to trial a policy change
 * without breaking pages.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $dev = $this->viteDevServerOrigin();
        $devWs = $dev ? preg_replace('#^http#', 'ws', $dev) : '';

        $csp = implode('; ', [
            "default-src 'self'",
            trim("script-src 'self' 'nonce-{$nonce}' {$dev}"),
            trim("style-src 'self' 'nonce-{$nonce}' {$dev}"),
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            trim("connect-src 'self' {$dev} {$devWs}"),
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);

        $headers = [
            config('security.csp_report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if (config('security.force_https')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /** The Vite dev server origin while `make dev` is running, else ''. */
    private function viteDevServerOrigin(): string
    {
        if (! app()->isLocal() || ! Vite::isRunningHot()) {
            return '';
        }

        $hot = trim((string) @file_get_contents(public_path('hot')));

        return preg_match('#^https?://[A-Za-z0-9.\-\[\]:]+$#', $hot) === 1 ? $hot : '';
    }
}
