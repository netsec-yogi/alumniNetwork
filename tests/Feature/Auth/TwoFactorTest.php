<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    public function test_privileged_user_without_2fa_is_sent_to_enrol(): void
    {
        $admin = $this->user(RoleName::VerificationOfficer);

        $this->actingAs($admin)->get('/admin')->assertRedirect(route('profile.security'));
        $this->actingAs($admin)->get('/admin/verification')->assertRedirect(route('profile.security'));
        $this->actingAs($admin)->get(route('profile.security'))->assertOk();
    }

    public function test_alumni_are_not_forced_to_enrol_by_default(): void
    {
        $this->actingAs($this->verifiedAlumnus()->user)->get('/dashboard')->assertOk();
    }

    public function test_sign_in_with_2fa_requires_a_valid_code(): void
    {
        $admin = $this->admin();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->totp()])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
    }

    /** SRS 104, test 8: a TOTP code cannot be replayed. */
    public function test_totp_code_cannot_be_reused(): void
    {
        $admin = $this->admin();
        $code = $this->totp();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code]);
        $this->assertAuthenticatedAs($admin);

        auth()->logout();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    /** SRS 104, test 7. */
    public function test_recovery_codes_are_single_use(): void
    {
        $admin = $this->admin();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'code-one-aaaa']);
        $this->assertAuthenticatedAs($admin);
        $this->assertNotContains('code-one-aaaa', $admin->fresh()->recoveryCodes());

        auth()->logout();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => 'code-one-aaaa']);
        $this->assertGuest();
    }

    public function test_failed_2fa_codes_count_towards_lockout(): void
    {
        config(['security.lockout.max_attempts' => 3]);
        $admin = $this->admin();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);
        foreach (['111111', '222222', '333333'] as $code) {
            $this->post('/two-factor-challenge', ['code' => $code]);
        }

        $this->assertTrue($admin->fresh()->isLocked());
    }

    /** SRS 104, test 6. */
    public function test_disabling_2fa_requires_password_confirmation(): void
    {
        $alumnus = $this->verifiedAlumnus()->user;
        $alumnus->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();

        $this->actingAs($alumnus)->delete('/user/two-factor-authentication')->assertRedirect(route('password.confirm'));
        $this->assertNotNull($alumnus->fresh()->two_factor_confirmed_at);

        $this->actingAs($alumnus)->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/two-factor-authentication')->assertRedirect();
        $this->assertNull($alumnus->fresh()->two_factor_confirmed_at);
    }

    public function test_mandatory_2fa_cannot_be_turned_off_by_its_owner(): void
    {
        $admin = $this->admin(RoleName::AlumniAdmin);

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/user/two-factor-authentication')->assertSessionHasErrors('two_factor');

        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
    }

    public function test_recovery_codes_cannot_be_read_back_later(): void
    {
        $alumnus = $this->verifiedAlumnus()->user;

        $this->actingAs($alumnus)->withSession(['auth.password_confirmed_at' => time()])
            ->getJson('/user/two-factor-recovery-codes')->assertNotFound();
    }

    public function test_admin_can_reset_a_users_2fa_and_it_is_audited(): void
    {
        $actor = $this->admin();
        $target = $this->admin(RoleName::VerificationOfficer);

        $this->actingAs($actor)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.users.reset-two-factor', $target), ['reason' => 'Lost phone; identity confirmed in person.'])
            ->assertRedirect();

        $this->assertNull($target->fresh()->two_factor_secret);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $target->id, 'action' => 'two_factor.reset_by_admin', 'user_id' => $actor->id]);
    }
}
