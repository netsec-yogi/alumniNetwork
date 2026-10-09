<?php

namespace App\Services\Auth;

use App\Enums\RoleName;
use App\Models\EmailOtpRequest;
use App\Models\User;
use App\Notifications\LoginOtp;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Passwordless sign-in for alumni by a code emailed to their registered
 * address. Everything about OTPs lives here: eligibility, generation,
 * storage (HMAC only), sending, verification, expiry, attempt limits,
 * single use and invalidation.
 *
 * Anti-enumeration: request() behaves the same for unknown, ineligible and
 * eligible emails (same pending state, same message); the controller pads
 * response time. Codes and hashes are never logged or audited.
 */
class EmailOtpLogin
{
    public const SENT = 'sent';

    public const SEND_FAILED = 'send_failed';

    public function __construct(private readonly OtpLoginSettings $settings, private readonly AuditLogger $audit) {}

    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }

    public static function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2) + [1 => ''];

        return Str::substr($local, 0, 1).str_repeat('*', 6).'@'.$domain;
    }

    /** The same eligibility rules as the rest of the app, plus: alumni only, no 2FA-mandatory staff. */
    public function ineligibility(?User $user): ?string
    {
        return match (true) {
            $user === null => 'unknown',
            ! $user->hasRole(RoleName::Alumni->value) => 'not_alumni',
            ! $user->isActive() => 'inactive',
            $user->isLocked() => 'locked',
            ! $user->hasVerifiedEmail() => 'email_unverified',
            // Staff accounts keep password + TOTP; email alone is too weak for them.
            $user->hasAnyRole(array_diff(config('security.two_factor_required_roles'), [RoleName::Alumni->value])) => 'privileged',
            default => null,
        };
    }

    /**
     * Handle an OTP request. Returns the pending state to keep in the session
     * (identical in shape whether or not a code was sent) and whether sending
     * failed (the only outcome the user is told about).
     *
     * @return array{pending: array<string, mixed>, outcome: string}
     */
    public function request(string $email, Request $request, bool $isResend = false): array
    {
        $email = self::normalize($email);
        $validity = $this->settings->get('validity_minutes');
        $cooldown = $this->settings->get('resend_cooldown_seconds');
        $pending = ['request_id' => null, 'email' => $email, 'masked' => self::mask($email), 'expires_at' => now()->addMinutes($validity)->timestamp, 'resend_at' => now()->addSeconds($cooldown)->timestamp, 'attempts' => 0];
        $event = $isResend ? 'otp_login.resend' : 'otp_login.requested';
        $context = ['email' => self::mask($email)];

        // Per-email cooldown and hourly cap stop anyone from bombing a mailbox.
        $cooldownKey = 'otp-cooldown:'.sha1($email);
        $hourKey = 'otp-email-hour:'.sha1($email);
        if (RateLimiter::tooManyAttempts($cooldownKey, 1) || RateLimiter::tooManyAttempts($hourKey, $this->settings->get('max_requests_per_hour'))) {
            $this->audit->record('otp_login.rate_limited', 'auth', null, null, $context, null, 'denied', $isResend ? 'resend' : 'request');
            Log::channel('security')->warning('Email OTP request rate-limited.', ['email' => self::mask($email), 'ip' => $request->ip()]);
            // Keep any code already sent usable; just don't send another.
            $existing = $this->latestUsable($email);

            return ['pending' => [...$pending, 'request_id' => $existing?->id, 'expires_at' => $existing?->expires_at->timestamp ?? $pending['expires_at'], 'resend_at' => now()->addSeconds(RateLimiter::availableIn($cooldownKey) ?: $cooldown)->timestamp], 'outcome' => self::SENT];
        }
        RateLimiter::hit($cooldownKey, $cooldown);
        RateLimiter::hit($hourKey, 3600);

        $user = User::where('email', $email)->first();
        $why = $this->ineligibility($user);
        $code = $this->generateCode(); // generated either way so timing doesn't tell
        $this->audit->record($event, 'auth', $user, null, $context);
        if ($why !== null) {
            // Unknown addresses are just "requested"; known but ineligible accounts are "blocked".
            if ($user !== null) {
                $this->audit->record('otp_login.blocked', 'auth', $user, null, $context, null, 'denied', $why);
            }

            return ['pending' => $pending, 'outcome' => self::SENT];
        }

        $otp = DB::transaction(function () use ($user, $email, $code, $validity, $request) {
            // A new code replaces any earlier one.
            EmailOtpRequest::where('user_id', $user->id)->whereNull('verified_at')->whereNull('invalidated_at')
                ->update(['invalidated_at' => now(), 'invalidated_reason' => 'superseded']);

            $otp = new EmailOtpRequest;
            $otp->forceFill([
                'user_id' => $user->id,
                'email' => $email,
                'otp_hash' => $this->hash($code),
                'expires_at' => now()->addMinutes($validity),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ])->save();

            return $otp;
        });

        try {
            // Sent now, not queued: if delivery fails the code must not stay usable.
            $user->notifyNow(new LoginOtp($code, $validity));
        } catch (Throwable $e) {
            $otp->forceFill(['invalidated_at' => now(), 'invalidated_reason' => 'send_failed'])->save();
            $this->audit->record('otp_login.send_failed', 'auth', $user, null, $context, null, 'failed', 'mail delivery failed');
            Log::channel('security')->error('Email OTP could not be sent.', ['user_id' => $user->id, 'error' => class_basename($e)]);

            return ['pending' => $pending, 'outcome' => self::SEND_FAILED];
        }

        $this->audit->record('otp_login.sent', 'auth', $user, null, $context, null, 'success', $isResend ? 'resend' : null);

        return ['pending' => [...$pending, 'request_id' => $otp->id, 'expires_at' => $otp->expires_at->timestamp], 'outcome' => self::SENT];
    }

    /**
     * Verify a submitted code against the pending request.
     *
     * @return array{user: ?User, error: ?string, pending: array<string, mixed>}
     */
    public function verify(array $pending, string $code): array
    {
        $max = $this->settings->get('max_attempts');
        $incorrect = 'The OTP entered is incorrect. Please try again.';

        return DB::transaction(function () use ($pending, $code, $max, $incorrect) {
            $otp = $pending['request_id'] ? EmailOtpRequest::whereKey($pending['request_id'])->lockForUpdate()->first() : null;

            // Expired: the same answer whether or not a real code exists.
            if (($otp?->expires_at->timestamp ?? $pending['expires_at']) < now()->timestamp) {
                if ($otp && $otp->invalidated_at === null && $otp->verified_at === null) {
                    $otp->forceFill(['invalidated_at' => now(), 'invalidated_reason' => 'expired'])->save();
                    $this->audit->record('otp_login.expired', 'auth', $otp->user, null, null, null, 'failed', 'expired');
                }

                return ['user' => null, 'error' => 'This OTP has expired. Please request a new one.', 'pending' => $pending];
            }

            $attempts = ($otp?->attempts ?? $pending['attempts']) + 1;
            $pending['attempts'] = $attempts;

            if (! $otp || ! $otp->isUsable()) {
                return ['user' => null, 'error' => $attempts >= $max ? 'Too many incorrect attempts. Please request a new OTP.' : $incorrect, 'pending' => $pending];
            }

            $otp->forceFill(['attempts' => $attempts])->save();

            if (! hash_equals($otp->otp_hash, $this->hash($code))) {
                $exhausted = $attempts >= $max;
                if ($exhausted) {
                    $otp->forceFill(['invalidated_at' => now(), 'invalidated_reason' => 'too_many_attempts'])->save();
                }
                $this->audit->record('otp_login.failed', 'auth', $otp->user, null, ['attempt' => $attempts], null, 'failed', $exhausted ? 'too many attempts — OTP invalidated' : 'incorrect OTP');

                return ['user' => null, 'error' => $exhausted ? 'Too many incorrect attempts. Please request a new OTP.' : $incorrect, 'pending' => $pending];
            }

            // Single use: consumed now, before the sign-in happens.
            $otp->forceFill(['verified_at' => now()])->save();
            $user = $otp->user;

            // Re-check: the account may have changed since the code was sent.
            if ($why = $this->ineligibility($user)) {
                $this->audit->record('otp_login.blocked', 'auth', $user, null, null, null, 'denied', $why);

                return ['user' => null, 'error' => $incorrect, 'pending' => $pending];
            }

            $this->audit->record('otp_login.verified', 'auth', $user, null, null, $user);

            return ['user' => $user, 'error' => null, 'pending' => $pending];
        });
    }

    private function latestUsable(string $email): ?EmailOtpRequest
    {
        return EmailOtpRequest::where('email', $email)->whereNull('verified_at')->whereNull('invalidated_at')->where('expires_at', '>', now())->latest('id')->first();
    }

    /** Cryptographically secure, uniformly distributed numeric code. */
    private function generateCode(): string
    {
        $length = $this->settings->get('length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', 'email-otp|'.$code, (string) config('app.key'));
    }
}
