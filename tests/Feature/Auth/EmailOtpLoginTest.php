<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Models\AuditLog;
use App\Models\EmailOtpRequest;
use App\Models\User;
use App\Notifications\LoginOtp;
use App\Services\Auth\Captcha;
use App\Services\Auth\OtpLoginSettings;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class EmailOtpLoginTest extends TestCase
{
    private function alumnus(): User
    {
        return $this->verifiedAlumnus()->user;
    }

    /** Requests an OTP with a planted, correct CAPTCHA answer. */
    private function requestOtp(string $email, string $captcha = 'ABCDE')
    {
        return $this->withSession([Captcha::sessionKey() => Captcha::payload('ABCDE')])
            ->post(route('login.otp.store'), ['email' => $email, 'captcha' => $captcha]);
    }

    private function sentCode(User $user): string
    {
        $code = null;
        Notification::assertSentTo($user, LoginOtp::class, function (LoginOtp $n) use ($user, &$code) {
            $code = trim($n->toMail($user)->introLines[1], '*');

            return true;
        });

        return $code;
    }

    public function test_an_eligible_alumnus_signs_in_with_the_emailed_code(): void
    {
        Notification::fake();
        $user = $this->alumnus();

        $this->requestOtp(strtoupper($user->email))
            ->assertRedirect(route('login.otp.show'))
            ->assertSessionHas('status', OtpLoginController::SENT_MESSAGE);

        $code = $this->sentCode($user);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $otp = EmailOtpRequest::sole();
        $this->assertNotSame($code, $otp->otp_hash);
        $this->assertSame(64, strlen($otp->otp_hash));

        $this->get(route('login.otp.show'))->assertInertia(fn (Assert $p) => $p
            ->component('Auth/OtpVerify')
            ->where('maskedEmail', fn ($m) => str_contains($m, '******@') && ! str_contains($m, $user->email))
            ->where('length', 6));

        $this->post(route('login.otp.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($otp->fresh()->verified_at);
        $this->assertTrue(AuditLog::where('action', 'otp_login.verified')->where('entity_id', $user->id)->exists());
    }

    public function test_unknown_and_ineligible_emails_get_the_same_response_and_no_email(): void
    {
        Notification::fake();
        $suspended = $this->alumnus();
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();
        $unverified = $this->user(RoleName::Alumni, ['email_verified_at' => null]);
        $staff = $this->admin(RoleName::EventManager);

        foreach (['nobody@example.com', $suspended->email, $unverified->email, $staff->email] as $email) {
            $this->flushSession();
            $this->requestOtp($email)
                ->assertRedirect(route('login.otp.show'))
                ->assertSessionHas('status', OtpLoginController::SENT_MESSAGE);
            $this->get(route('login.otp.show'))->assertInertia(fn (Assert $p) => $p->component('Auth/OtpVerify'));
        }

        Notification::assertNothingSent();
        $this->assertSame(0, EmailOtpRequest::count());
        $this->assertSame(3, AuditLog::where('action', 'otp_login.blocked')->count());

        // Guessing on the fake verification screen fails like a wrong code.
        $this->post(route('login.otp.verify'), ['code' => '123456'])->assertSessionHasErrors(['code' => 'The OTP entered is incorrect. Please try again.']);
        $this->assertGuest();
    }

    public function test_a_wrong_captcha_is_refused_without_sending(): void
    {
        Notification::fake();
        $user = $this->alumnus();

        $this->requestOtp($user->email, 'WRONG')->assertSessionHasErrors('captcha');
        // No challenge in the session at all (e.g. scripted request).
        $this->post(route('login.otp.store'), ['email' => $user->email, 'captcha' => 'ABCDE'])->assertSessionHasErrors('captcha');

        Notification::assertNothingSent();
    }

    public function test_the_captcha_image_is_served_uncached(): void
    {
        $response = $this->get(route('captcha'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertIsArray(session(Captcha::sessionKey()));
    }

    public function test_wrong_codes_are_limited_and_the_otp_is_invalidated(): void
    {
        Notification::fake();
        $user = $this->alumnus();
        $this->requestOtp($user->email);
        $code = $this->sentCode($user);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('login.otp.verify'), ['code' => $wrong])->assertSessionHasErrors(['code' => 'The OTP entered is incorrect. Please try again.']);
        }
        $this->post(route('login.otp.verify'), ['code' => $wrong])->assertSessionHasErrors(['code' => 'Too many incorrect attempts. Please request a new OTP.']);

        // Even the right code is useless now.
        $this->post(route('login.otp.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertSame('too_many_attempts', EmailOtpRequest::sole()->invalidated_reason);
        $this->assertSame(5, AuditLog::where('action', 'otp_login.failed')->count());
    }

    public function test_expired_codes_are_refused(): void
    {
        Notification::fake();
        $user = $this->alumnus();
        $this->requestOtp($user->email);
        $code = $this->sentCode($user);

        $this->travel(16)->minutes();
        $this->post(route('login.otp.verify'), ['code' => $code])->assertSessionHasErrors(['code' => 'This OTP has expired. Please request a new one.']);
        $this->assertGuest();
        $this->assertSame('expired', EmailOtpRequest::sole()->invalidated_reason);
        $this->assertTrue(AuditLog::where('action', 'otp_login.expired')->exists());
    }

    public function test_a_code_works_only_once(): void
    {
        Notification::fake();
        $user = $this->alumnus();
        $this->requestOtp($user->email);
        $code = $this->sentCode($user);
        $pending = session('otp_login.pending');

        $this->post(route('login.otp.verify'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        // Replaying the same code with the same pending state fails.
        $this->withSession(['otp_login.pending' => $pending])->post(route('login.otp.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_resend_respects_the_cooldown_and_replaces_the_previous_code(): void
    {
        Notification::fake();
        $user = $this->alumnus();
        $this->requestOtp($user->email);
        $first = $this->sentCode($user);

        $this->post(route('login.otp.resend'))->assertSessionHasErrors('code');
        Notification::assertSentToTimes($user, LoginOtp::class, 1);

        $this->travel(61)->seconds();
        $this->post(route('login.otp.resend'))->assertRedirect(route('login.otp.show'));
        Notification::assertSentToTimes($user, LoginOtp::class, 2);

        $this->assertSame('superseded', EmailOtpRequest::orderBy('id')->first()->invalidated_reason);
        $this->assertTrue(AuditLog::where('action', 'otp_login.resend')->exists());

        // The first code no longer works (unless the RNG repeated itself).
        $second = EmailOtpRequest::orderByDesc('id')->first();
        if (! hash_equals($second->otp_hash, hash_hmac('sha256', 'email-otp|'.$first, (string) config('app.key')))) {
            $this->post(route('login.otp.verify'), ['code' => $first])->assertSessionHasErrors('code');
        }
    }

    public function test_requests_per_email_are_rate_limited(): void
    {
        Notification::fake();
        $user = $this->alumnus();
        app(OtpLoginSettings::class)->save(['max_requests_per_hour' => 2, 'resend_cooldown_seconds' => 30]);

        foreach (range(1, 3) as $i) {
            $this->flushSession();
            $this->requestOtp($user->email)->assertSessionHas('status', OtpLoginController::SENT_MESSAGE);
            $this->travel(31)->seconds();
        }

        Notification::assertSentToTimes($user, LoginOtp::class, 2);
        $this->assertTrue(AuditLog::where('action', 'otp_login.rate_limited')->exists());
    }

    public function test_a_failed_email_leaves_no_usable_code(): void
    {
        $user = $this->alumnus();
        $this->mock(Dispatcher::class, fn ($m) => $m->shouldReceive('sendNow')->andThrow(new RuntimeException('SMTP down')));

        $this->requestOtp($user->email)
            ->assertRedirect(route('login', ['mode' => 'otp']))
            ->assertSessionHas('error', OtpLoginController::FAILED_MESSAGE);

        $this->assertSame('send_failed', EmailOtpRequest::sole()->invalidated_reason);
        $this->assertTrue(AuditLog::where('action', 'otp_login.send_failed')->exists());
        $this->get(route('login.otp.show'))->assertRedirect(route('login'));
    }

    public function test_alumni_with_two_factor_still_get_the_totp_challenge(): void
    {
        Notification::fake();
        $user = $this->alumnus();
        $user->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();

        $this->requestOtp($user->email);
        $this->post(route('login.otp.verify'), ['code' => $this->sentCode($user)])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_the_audit_log_never_contains_the_code_or_its_hash(): void
    {
        Notification::fake();
        $user = $this->alumnus();
        $this->requestOtp($user->email);
        $code = $this->sentCode($user);
        $this->post(route('login.otp.verify'), ['code' => $code === '000000' ? '111111' : '000000']);
        $this->post(route('login.otp.verify'), ['code' => $code]);

        $json = AuditLog::all()->toJson();
        $this->assertStringNotContainsString($code, $json);
        $this->assertStringNotContainsString(EmailOtpRequest::sole()->otp_hash, $json);
        $this->assertStringNotContainsString($user->email, $json);
    }

    public function test_admins_with_security_manage_can_change_the_settings(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.settings.otp'))->assertInertia(fn (Assert $p) => $p->component('Admin/Settings/Otp')->where('values.validity_minutes', 15));

        $this->actingAs($admin)->put(route('admin.settings.otp.update'), ['length' => 8, 'validity_minutes' => 10, 'max_attempts' => 3, 'resend_cooldown_seconds' => 90, 'max_requests_per_hour' => 4])->assertSessionHasNoErrors();
        $this->assertSame(8, app(OtpLoginSettings::class)->get('length'));

        $this->actingAs($admin)->put(route('admin.settings.otp.update'), ['length' => 4, 'validity_minutes' => 10, 'max_attempts' => 3, 'resend_cooldown_seconds' => 90, 'max_requests_per_hour' => 4])->assertSessionHasErrors('length');

        $this->actingAs($this->admin(RoleName::EventManager))->get(route('admin.settings.otp'))->assertForbidden();
    }
}
