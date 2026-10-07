<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

/**
 * The WebAuthn ceremony itself is covered end-to-end by the browser smoke
 * test (virtual authenticator); these cover the rules around it.
 */
class PasskeyTest extends TestCase
{
    private function passkeyFor(User $user, string $name = 'Laptop'): Passkey
    {
        return Passkey::forceCreate(['user_id' => $user->id, 'name' => $name, 'credential_id' => bin2hex(random_bytes(16)), 'credential' => ['test' => true]]);
    }

    public function test_adding_a_passkey_needs_a_confirmed_password(): void
    {
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->getJson('/user/passkeys/options')->assertStatus(423);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->getJson('/user/passkeys/options')
            ->assertOk()
            ->assertJsonPath('options.authenticatorSelection.userVerification', 'required')
            ->assertJsonPath('options.authenticatorSelection.residentKey', 'required');
    }

    public function test_sign_in_options_require_user_verification_and_are_guest_only(): void
    {
        $this->getJson('/passkeys/login/options')->assertOk()->assertJsonPath('options.userVerification', 'required');

        $this->actingAs($this->verifiedAlumnus()->user)->getJson('/passkeys/login/options')->assertStatus(302);
    }

    public function test_locked_and_suspended_accounts_cannot_sign_in_with_a_passkey(): void
    {
        $active = $this->verifiedAlumnus()->user;
        $this->assertTrue(Passkeys::allowsLogin(Request::create('/'), $this->passkeyFor($active)));

        $suspended = $this->verifiedAlumnus()->user;
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();
        $locked = $this->verifiedAlumnus()->user;
        $locked->forceFill(['locked_until' => now()->addHour()])->save();

        foreach ([$suspended, $locked] as $user) {
            try {
                Passkeys::allowsLogin(Request::create('/'), $this->passkeyFor($user->fresh()));
                $this->fail('Passkey sign-in should have been refused.');
            } catch (ValidationException) {
                $this->assertDatabaseHas('audit_logs', ['action' => 'login.blocked_passkey', 'user_id' => $user->id]);
            }
        }
    }

    public function test_only_the_owner_can_remove_a_passkey_and_it_is_audited(): void
    {
        $owner = $this->verifiedAlumnus()->user;
        $other = $this->verifiedAlumnus()->user;
        $passkey = $this->passkeyFor($owner);

        $this->actingAs($other)->withSession(['auth.password_confirmed_at' => time()])->delete("/user/passkeys/{$passkey->id}")->assertForbidden();
        $this->assertModelExists($passkey);

        $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()])->delete("/user/passkeys/{$passkey->id}")->assertRedirect();
        $this->assertModelMissing($passkey);
        $this->assertDatabaseHas('audit_logs', ['action' => 'passkey.deleted', 'user_id' => $owner->id]);
    }

    public function test_registration_is_audited_and_listed_on_the_security_page(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $passkey = $this->passkeyFor($user, 'Pixel 9');
        PasskeyRegistered::dispatch($user, $passkey);

        $this->assertDatabaseHas('audit_logs', ['action' => 'passkey.registered', 'user_id' => $user->id]);
        $this->actingAs($user)->get(route('profile.security'))->assertInertia(fn ($p) => $p->where('passkeys.0.name', 'Pixel 9'));
    }
}
