<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyDisable;

/**
 * Users whose role makes 2FA mandatory cannot switch it off themselves
 * (SRS 11). Abandoning an unconfirmed enrolment is still allowed. A lost
 * device is handled by an administrator's audited reset instead.
 */
class DisableTwoFactorAuthentication extends FortifyDisable
{
    public function __invoke($user)
    {
        if ($user->two_factor_confirmed_at !== null && $user->requiresTwoFactor()) {
            throw ValidationException::withMessages([
                'two_factor' => __('Two-factor authentication is mandatory for your role and cannot be turned off.'),
            ]);
        }

        parent::__invoke($user);
    }
}
