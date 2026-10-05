<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    private const PASSWORD = 'password';

    public function test_active_user_can_sign_in_and_it_is_audited(): void
    {
        $user = $this->user(RoleName::Alumni);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'login.succeeded']);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    /** SRS 104, test 4. */
    public function test_suspended_user_cannot_authenticate(): void
    {
        $user = $this->user(RoleName::Alumni, ['status' => 'suspended']);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_suspended_mid_session_is_signed_out(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_account_locks_after_repeated_failures_and_refuses_the_right_password(): void
    {
        config(['security.lockout.max_attempts' => 3]);
        $user = $this->user(RoleName::Alumni);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->assertTrue($user->fresh()->isLocked());
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $user->id, 'action' => 'account.locked']);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** SRS 104, test 9. */
    public function test_login_is_rate_limited_per_account_and_ip(): void
    {
        $user = $this->user(RoleName::Alumni);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        // Even the correct password is refused while throttled.
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => __('Too many sign-in attempts. Please try again in 60 seconds.')]);
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login.rate_limited']);
    }

    /** SRS 104, test 14. */
    public function test_passwords_never_reach_the_audit_log(): void
    {
        $user = $this->user(RoleName::Alumni);
        $this->post('/login', ['email' => $user->email, 'password' => 'a-very-distinctive-wrong-password']);
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);

        $dump = AuditLog::all()->toJson();
        $this->assertStringNotContainsString('a-very-distinctive-wrong-password', $dump);
        $this->assertStringNotContainsString($user->password, $dump);

        $logs = collect(glob(storage_path('logs/*.log')))->map(fn ($f) => file_get_contents($f))->implode('');
        $this->assertStringNotContainsString('a-very-distinctive-wrong-password', $logs);
    }

    public function test_audit_logger_redacts_nested_secrets(): void
    {
        $redacted = app(AuditLogger::class)->redact([
            'name' => 'x',
            'nested' => ['password' => 'p', 'deeper' => ['two_factor_secret' => 's', 'Code' => '123456']],
        ]);

        $this->assertSame('x', $redacted['name']);
        $this->assertSame('[redacted]', $redacted['nested']['password']);
        $this->assertSame('[redacted]', $redacted['nested']['deeper']['two_factor_secret']);
        $this->assertSame('[redacted]', $redacted['nested']['deeper']['Code']);
    }
}
