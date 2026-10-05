<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;

/**
 * Password and 2FA settings (SRS 12-15). The 2FA actions themselves are
 * Fortify's endpoints; this page renders their state.
 */
class SecurityController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $status = $request->session()->get('status');

        $pendingConfirmation = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;

        // Recovery codes are shown once, straight after they are generated
        // (SRS 12), and never again.
        $showCodes = $user->two_factor_confirmed_at !== null
            && in_array($status, [Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED, Fortify::RECOVERY_CODES_GENERATED], true);

        return Inertia::render('Profile/Security', [
            'twoFactor' => [
                'enabled' => $user->two_factor_confirmed_at !== null,
                'required' => $user->requiresTwoFactor(),
                'pendingConfirmation' => $pendingConfirmation,
                'qrCodeSvg' => $pendingConfirmation ? $user->twoFactorQrCodeSvg() : null,
                'setupKey' => $pendingConfirmation ? decrypt($user->two_factor_secret) : null,
                'recoveryCodes' => $showCodes ? $user->recoveryCodes() : null,
            ],
            'passwordChangedAt' => $user->password_changed_at?->toDayDateTimeString(),
            'status' => $status,
        ]);
    }
}
