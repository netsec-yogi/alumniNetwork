<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Services\AuditLogger;
use App\Services\SessionManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private readonly SessionManager $sessions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Reset via emailed token. Whoever held the old password may still be
     * signed in, so every session and "remember me" token is revoked
     * (SRS 69), and a reset also clears any lockout.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'lock_reason' => null,
        ])->save();

        $revoked = $this->sessions->revokeAll($user);

        $this->audit->record('password.reset', 'auth', $user, null, ['sessions_revoked' => $revoked], $user);
        $user->notify(new PasswordChanged(request()->ip()));
    }
}
