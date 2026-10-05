<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\AccountLockout;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Credential check for Fortify's login pipeline (SRS 9, 67-68).
 *
 * Order matters: the lockout is checked before the password, so a locked
 * account cannot be used as a password oracle; account status is checked
 * after it, so a suspended account is only revealed to someone who already
 * knows its password.
 */
class AuthenticateUser
{
    public function __construct(
        private readonly AccountLockout $lockout,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(Request $request): ?User
    {
        $email = Str::lower((string) $request->input(Fortify::username()));
        $password = (string) $request->input('password');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            // Spend the same time as a real check so response timing does not
            // reveal which addresses have accounts.
            Hash::check($password, '$2y$12$'.str_repeat('a', 53));

            return null;
        }

        if ($user->isLocked()) {
            $this->audit->record('login.blocked_locked', 'auth', $user, null, null, $user);

            throw ValidationException::withMessages([
                Fortify::username() => __('This account is temporarily locked after repeated failed sign-in attempts. Try again after :time, or reset your password.', [
                    'time' => $user->locked_until->timezone(config('app.timezone'))->format('H:i'),
                ]),
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            $this->lockout->registerFailure($user, 'password');

            return null;
        }

        if (! $user->isActive()) {
            $this->audit->record('login.blocked_inactive', 'auth', $user, null, ['status' => $user->status->value], $user);

            throw ValidationException::withMessages([
                Fortify::username() => __('This account is not active. Please contact the alumni office.'),
            ]);
        }

        Auth::getProvider()->rehashPasswordIfRequired($user, ['password' => $password]);

        return $user;
    }
}
