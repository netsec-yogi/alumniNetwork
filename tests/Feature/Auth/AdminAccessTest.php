<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Notifications\NewDeviceSignIn;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    private function signInWithTotp($admin, array $cookies = []): TestResponse
    {
        $this->withCookies($cookies)->post('/login', ['email' => $admin->email, 'password' => 'password']);

        return $this->withCookies($cookies)->post('/two-factor-challenge', ['code' => $this->totp()]);
    }

    private function deviceCookie($user): string
    {
        return 'kd_'.substr(hash_hmac('sha256', (string) $user->id, (string) config('app.key')), 0, 16);
    }

    public function test_admin_signing_in_from_a_new_browser_is_alerted(): void
    {
        Notification::fake();
        $admin = $this->admin(attributes: ['last_login_at' => now()->subWeek()]);

        $response = $this->signInWithTotp($admin);

        $this->assertAuthenticatedAs($admin);
        Notification::assertSentTo($admin, NewDeviceSignIn::class);
        $response->assertCookie($this->deviceCookie($admin), '1');
        $this->assertDatabaseHas('audit_logs', ['action' => 'login.new_device', 'user_id' => $admin->id]);
    }

    public function test_known_browser_is_not_alerted(): void
    {
        Notification::fake();
        $known = $this->admin(attributes: ['last_login_at' => now()->subWeek()]);

        $this->signInWithTotp($known, [$this->deviceCookie($known) => '1']);

        $this->assertAuthenticatedAs($known);
        Notification::assertNothingSent();
    }

    public function test_first_ever_sign_in_is_not_alerted(): void
    {
        Notification::fake();
        $fresh = $this->admin(RoleName::AlumniAdmin);

        $this->signInWithTotp($fresh);

        $this->assertAuthenticatedAs($fresh);
        Notification::assertNothingSent();
    }

    public function test_regular_members_are_not_alerted_unless_configured(): void
    {
        Notification::fake();
        $user = $this->verifiedAlumnus()->user;
        $user->forceFill(['last_login_at' => now()->subWeek()])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        Notification::assertNothingSent();

        auth()->logout();
        config(['security.login_alerts' => 'all']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        Notification::assertSentTo($user, NewDeviceSignIn::class);
    }

    public function test_admin_area_honours_the_ip_allow_list(): void
    {
        $admin = $this->admin();
        config(['security.admin_allowed_ips' => ['10.20.0.0/16']]);

        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/admin')->assertForbidden();
        $this->assertSame(1, AuditLog::where('action', 'admin.ip_blocked')->count());

        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '10.20.4.5'])->get('/admin')->assertOk();

        // Member pages are unaffected.
        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/dashboard')->assertOk();
    }

    public function test_empty_allow_list_means_no_restriction(): void
    {
        config(['security.admin_allowed_ips' => []]);

        $this->actingAs($this->admin())->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/admin')->assertOk();
    }
}
