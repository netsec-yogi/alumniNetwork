<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    private function fakeSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.1',
            'user_agent' => 'Test', 'payload' => '', 'last_activity' => time(),
        ]);
    }

    /** SRS 104, test 5. */
    public function test_changing_password_signs_out_other_devices(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $this->fakeSession($user, 'other-device-session');
        $rememberToken = $user->remember_token;

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'password',
            'password' => 'a brand new passphrase',
            'password_confirmation' => 'a brand new passphrase',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device-session']);
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'password.changed']);
    }

    public function test_password_reset_signs_out_everywhere_and_clears_lockout(): void
    {
        $user = $this->user(RoleName::Alumni, ['locked_until' => now()->addHour(), 'failed_login_attempts' => 9]);
        $this->fakeSession($user, 'stolen-session');
        $token = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'a brand new passphrase', 'password_confirmation' => 'a brand new passphrase',
        ])->assertRedirect('/login');

        $this->assertDatabaseMissing('sessions', ['id' => 'stolen-session']);
        $this->assertFalse($user->fresh()->isLocked());
    }

    public function test_reset_link_requests_are_rate_limited(): void
    {
        $user = $this->user(RoleName::Alumni);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => $user->email]);
        }

        $this->post('/forgot-password', ['email' => $user->email])->assertStatus(429);
    }

    public function test_new_password_must_meet_policy(): void
    {
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
    }
}
