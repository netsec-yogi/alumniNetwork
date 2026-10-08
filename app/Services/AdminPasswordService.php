<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordChangedByAdmin;
use App\Notifications\PasswordResetByAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Administrator password operations. An administrator can never see or
 * retrieve a password: they either SET a new one (change) or invalidate the
 * current one and start the normal emailed reset (reset).
 *
 * Both revoke every session and "remember me" token, notify the user, and
 * write an audit entry that records that the operation happened — never a
 * password, hash or token.
 */
class AdminPasswordService
{
    public const CHANGED = 'password_changed_by_admin';

    public const RESET = 'password_reset_by_admin';

    public function __construct(private readonly SessionManager $sessions, private readonly AuditLogger $audit) {}

    public function change(User $admin, User $user, string $password, bool $requireChange, string $reason): void
    {
        DB::transaction(function () use ($user, $password, $requireChange) {
            $user->forceFill([
                'password' => Hash::make($password),
                'password_changed_at' => now(),
                'password_change_required' => $requireChange,
                // Invalidate "remember me" cookies.
                'remember_token' => Str::random(60),
            ])->save();
        });
        $revoked = $this->sessions->revokeAll($user);

        $user->notify(new PasswordChangedByAdmin);
        $this->audit->record(self::CHANGED, 'security', $user, null, [
            'require_change_at_next_sign_in' => $requireChange,
            'sessions_revoked' => $revoked,
        ], $admin, 'success', $reason);
    }

    /**
     * The current password stops working immediately (replaced by a random
     * hash nobody knows) and the user is emailed a standard reset link to
     * choose a new one. No temporary password exists to leak.
     */
    public function reset(User $admin, User $user, string $reason): void
    {
        $user->forceFill([
            'password' => Hash::make(Str::random(64)),
            'password_changed_at' => now(),
            'password_change_required' => false,
            'remember_token' => Str::random(60),
        ])->save();
        $revoked = $this->sessions->revokeAll($user);

        $token = Password::broker()->createToken($user);
        $user->notify(new PasswordResetByAdmin($token));

        $this->audit->record(self::RESET, 'security', $user, null, [
            'reset_link_sent' => true,
            'sessions_revoked' => $revoked,
        ], $admin, 'success', $reason);
    }

    /** Refused or invalid attempts are audited too (without any submitted password). */
    public function recordAttempt(string $action, User $admin, User $user, string $status, string $detail, ?string $reason = null): void
    {
        $this->audit->record($action, 'security', $user, null, ['outcome' => $detail], $admin, $status, $reason);
    }
}
