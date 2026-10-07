<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional IP allow-list for the admin area (SRS 66). Off while
 * ADMIN_ALLOWED_IPS is empty. Behind a proxy this depends on TRUSTED_PROXIES
 * being right, or every request will appear to come from the proxy.
 */
class RestrictAdminByIp
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('security.admin_allowed_ips');

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            $this->audit->record('admin.ip_blocked', 'security', $request->user(), null, ['path' => $request->path()]);
            abort(403, 'The admin area is not available from this network.');
        }

        return $next($request);
    }
}
