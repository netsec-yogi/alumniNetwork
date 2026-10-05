<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\AuditLog;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class PlatformSecurityTest extends TestCase
{
    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_only_when_https_is_enforced(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        config(['security.force_https' => true]);
        $this->get('/login')->assertHeader('Strict-Transport-Security');
    }

    /** SRS 81. */
    public function test_request_id_is_returned_and_recorded_in_the_audit_log(): void
    {
        $user = $this->user(RoleName::Alumni);

        $response = $this->withHeader('X-Request-ID', 'trace-1234-abcd')
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertHeader('X-Request-ID', 'trace-1234-abcd');
        $this->assertSame('trace-1234-abcd', AuditLog::where('action', 'login.succeeded')->value('request_id'));
    }

    public function test_malformed_request_ids_are_replaced(): void
    {
        $id = $this->withHeader('X-Request-ID', "bad\nid<script>")->get('/login')->headers->get('X-Request-ID');

        $this->assertMatchesRegularExpression('/^[0-9a-f\-]{36}$/', $id);
    }

    /** SRS 104, test 15. */
    public function test_production_errors_do_not_expose_internals(): void
    {
        Route::middleware('web')->get('/__boom', fn () => throw new RuntimeException('SQLSTATE secret-dsn /var/www/html/app/Secret.php'));
        config(['app.debug' => false]);
        $this->app['env'] = 'production';

        $response = $this->get('/__boom');

        $response->assertStatus(500);
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('/var/www/html');
        $response->assertDontSee('Stack trace');
    }

    public function test_audit_entries_are_immutable(): void
    {
        $this->user(RoleName::Alumni); // role assignment writes an entry
        $entry = AuditLog::firstOrFail();

        $this->expectException(\LogicException::class);
        $entry->update(['action' => 'tampered']);
    }

    public function test_csrf_protection_guards_every_browser_route(): void
    {
        // Laravel bypasses the check itself while running tests, so assert
        // the middleware is wired into the web group instead.
        $web = app(Kernel::class)->getMiddlewareGroups()['web'];

        // Laravel 13 name for CSRF verification (formerly ValidateCsrfToken).
        $this->assertContains(PreventRequestForgery::class, $web);
    }
}
