<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Per-account lockout (SRS 68). Counts failed passwords and failed 2FA codes
 * alike: a stolen password must not buy unlimited TOTP guesses.
 */
class AccountLockout
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function registerFailure(User $user, string $factor): void
    {
        $max = config('security.lockout.max_attempts');

        $user->increment('failed_login_attempts');
        $attempts = $user->failed_login_attempts;

        $this->audit->record('login.failed', 'auth', $user, null, ['factor' => $factor, 'attempt' => $attempts], $user);

        if ($attempts >= $max) {
            $this->lock($user, "{$attempts} consecutive failed sign-in attempts ({$factor})");
        }
    }

    public function lock(User $user, string $reason, ?User $actor = null): void
    {
        $user->forceFill([
            'locked_until' => now()->addMinutes(config('security.lockout.minutes')),
            'lock_reason' => $reason,
        ])->save();

        $this->audit->record('account.locked', 'security', $user, null, ['reason' => $reason, 'until' => $user->locked_until->toIso8601String()], $actor ?? $user);
        Log::channel('security')->warning('Account locked.', ['user_id' => $user->id, 'reason' => $reason, 'ip' => request()->ip()]);
    }

    public function unlock(User $user, User $actor): void
    {
        $before = ['locked_until' => $user->locked_until?->toIso8601String(), 'lock_reason' => $user->lock_reason];

        $user->forceFill(['locked_until' => null, 'lock_reason' => null, 'failed_login_attempts' => 0])->save();

        $this->audit->record('account.unlocked', 'security', $user, $before, ['locked_until' => null], $actor);
    }

    public function reset(User $user): void
    {
        if ($user->failed_login_attempts > 0 || $user->locked_until !== null) {
            $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null, 'lock_reason' => null])->save();
        }
    }
}
