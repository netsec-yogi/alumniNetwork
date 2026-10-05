<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AccountLockout;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Verified;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

/**
 * Routes authentication, 2FA and role events into the audit log (SRS 80).
 *
 * Role changes are audited here, from the permission package's own events,
 * so that every change is captured however it was made -- admin UI, tinker
 * or a seeder.
 */
class SecurityEventSubscriber
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AccountLockout $lockout,
    ) {}

    public function onLogin(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $this->lockout->reset($user);

        // Start of the absolute session limit for privileged users.
        if (request()->hasSession()) {
            request()->session()->put('auth.started_at', now()->getTimestamp());
            request()->session()->put('auth.last_seen_at', now()->getTimestamp());
        }

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();

        $this->audit->record('login.succeeded', 'auth', $user, null, ['remember' => $event->remember], $user);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user) {
            $this->audit->record('logout', 'auth', $event->user, null, null, $event->user);
        }
    }

    /**
     * Known accounts are already audited by AccountLockout; this keeps a
     * record of guesses against addresses with no account at all.
     */
    public function onFailed(Failed $event): void
    {
        Log::channel('security')->notice('Failed sign-in.', [
            'email' => $event->credentials['email'] ?? null,
            'ip' => request()->ip(),
            'request_id' => request()->attributes->get('request_id'),
        ]);
    }

    public function onRateLimited(Lockout $event): void
    {
        $this->audit->record('login.rate_limited', 'security', null, null, [
            'email' => $event->request->input('email'),
            'path' => $event->request->path(),
        ]);

        Log::channel('security')->warning('Authentication rate limit hit.', [
            'email' => $event->request->input('email'),
            'ip' => $event->request->ip(),
        ]);
    }

    public function onEmailVerified(Verified $event): void
    {
        $this->audit->record('email.verified', 'auth', $event->user, null, null, $event->user);
    }

    public function onTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->lockout->registerFailure($event->user, 'two_factor');
    }

    public function onTwoFactorChange(object $event): void
    {
        $action = match ($event::class) {
            TwoFactorAuthenticationEnabled::class => 'two_factor.enabled',
            TwoFactorAuthenticationConfirmed::class => 'two_factor.confirmed',
            TwoFactorAuthenticationDisabled::class => 'two_factor.disabled',
            RecoveryCodesGenerated::class => 'two_factor.recovery_codes_generated',
            RecoveryCodeReplaced::class => 'two_factor.recovery_code_used',
        };

        // Only the fact of the change is logged -- never the secret or codes.
        $this->audit->record($action, 'security', $event->user, null, null, $event->user);
    }

    public function onRoleAttached(RoleAttachedEvent $event): void
    {
        $this->audit->record('role.assigned', 'rbac', $event->model, null, ['roles' => $this->roleNames($event->rolesOrIds)]);
    }

    public function onRoleDetached(RoleDetachedEvent $event): void
    {
        $this->audit->record('role.removed', 'rbac', $event->model, ['roles' => $this->roleNames($event->rolesOrIds)], null);
    }

    /** @return list<string> */
    private function roleNames(mixed $rolesOrIds): array
    {
        return collect(is_iterable($rolesOrIds) ? $rolesOrIds : [$rolesOrIds])
            ->map(fn ($r) => $r instanceof Role ? $r->name : Role::find($r)?->name ?? (string) $r)
            ->values()
            ->all();
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            Failed::class => 'onFailed',
            Lockout::class => 'onRateLimited',
            Verified::class => 'onEmailVerified',
            TwoFactorAuthenticationFailed::class => 'onTwoFactorFailed',
            TwoFactorAuthenticationEnabled::class => 'onTwoFactorChange',
            TwoFactorAuthenticationConfirmed::class => 'onTwoFactorChange',
            TwoFactorAuthenticationDisabled::class => 'onTwoFactorChange',
            RecoveryCodesGenerated::class => 'onTwoFactorChange',
            RecoveryCodeReplaced::class => 'onTwoFactorChange',
            RoleAttachedEvent::class => 'onRoleAttached',
            RoleDetachedEvent::class => 'onRoleDetached',
        ];
    }
}
