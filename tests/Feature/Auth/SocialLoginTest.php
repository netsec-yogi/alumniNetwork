<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google' => ['client_id' => 'id', 'client_secret' => 'secret', 'redirect' => 'http://localhost/auth/google/callback']]);
    }

    private function identity(string $id = 'g-123', string $email = 'someone@gmail.com'): SocialiteUser
    {
        return (new SocialiteUser)->map(['id' => $id, 'email' => $email, 'name' => 'Someone']);
    }

    private function linked(User $user, string $id = 'g-123'): void
    {
        SocialAccount::make()->forceFill(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => $id, 'email' => 'someone@gmail.com'])->save();
    }

    public function test_unconfigured_providers_are_hidden_and_404(): void
    {
        $this->get('/auth/linkedin-openid/redirect')->assertNotFound();
        $this->get('/login')->assertInertia(fn ($p) => $p->where('socialProviders', ['google' => 'Google']));
    }

    public function test_a_linked_account_signs_in(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $this->linked($user);
        Socialite::fake('google', $this->identity());

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_matching_email_alone_never_signs_in(): void
    {
        $user = $this->verifiedAlumnus()->user;
        Socialite::fake('google', $this->identity('g-999', $user->email));

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('warning');
        $this->assertGuest();
    }

    public function test_two_factor_still_applies(): void
    {
        $admin = $this->admin();
        $this->linked($admin);
        Socialite::fake('google', $this->identity());

        $this->get('/auth/google/callback')->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $this->totp()]);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_suspended_accounts_are_refused(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $user->forceFill(['status' => UserStatus::Suspended])->save();
        $this->linked($user);
        Socialite::fake('google', $this->identity());

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_linking_needs_a_confirmed_password_and_then_links(): void
    {
        $user = $this->verifiedAlumnus()->user;
        Socialite::fake('google', $this->identity());

        $this->actingAs($user)->post('/profile/social/google')->assertRedirect(route('password.confirm'));

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post('/profile/social/google');
        $this->get('/auth/google/callback')->assertRedirect(route('profile.security'))->assertSessionHas('success');

        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'g-123']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'social.linked', 'user_id' => $user->id]);
    }

    public function test_an_identity_cannot_be_linked_to_two_members(): void
    {
        $this->linked($this->verifiedAlumnus()->user);
        $other = $this->verifiedAlumnus()->user;
        Socialite::fake('google', $this->identity());

        $this->actingAs($other)->withSession(['auth.password_confirmed_at' => time()])->post('/profile/social/google');
        $this->get('/auth/google/callback')->assertSessionHas('error');

        $this->assertSame(1, SocialAccount::count());
    }

    public function test_a_signed_in_user_hitting_the_login_callback_is_not_switched(): void
    {
        $other = $this->verifiedAlumnus()->user;
        $this->linked($other);
        $me = $this->verifiedAlumnus()->user;
        Socialite::fake('google', $this->identity());

        $this->actingAs($me)->get('/auth/google/callback')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($me);
    }

    public function test_unlinking(): void
    {
        $user = $this->verifiedAlumnus()->user;
        $this->linked($user);

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->delete('/profile/social/google')->assertRedirect();

        $this->assertSame(0, SocialAccount::count());
    }
}
