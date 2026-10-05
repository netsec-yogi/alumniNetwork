<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Services\AuditLogger;
use App\Services\SessionManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(
        private readonly SessionManager $sessions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Change password while signed in. Every other device is signed out
     * (SRS 104, test 5); the current session continues.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => array_merge($this->passwordRules(), ['different:current_password']),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
            'password.different' => __('Choose a password different from your current one.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        // Deleting the session rows signs other browsers out; rotating the
        // remember token stops a "remember me" cookie from signing them back in.
        $revoked = $this->sessions->revokeAll($user, request()->session()->getId());

        $this->audit->record('password.changed', 'auth', $user, null, ['other_sessions_revoked' => $revoked], $user);
        $user->notify(new PasswordChanged(request()->ip()));
    }
}
