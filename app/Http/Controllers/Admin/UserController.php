<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use App\Notifications\EmailChangedByAdmin;
use App\Services\AccountLockout;
use App\Services\AdminPasswordService;
use App\Services\AuditLogger;
use App\Services\RoleAssignment;
use App\Services\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** User administration (SRS 68, 79, 97). Every mutation is policy-checked. */
class UserController extends Controller
{
    public function __construct(
        private readonly RoleAssignment $roles,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::enum(RoleName::class)],
            'status' => ['nullable', Rule::in([...array_column(UserStatus::cases(), 'value'), 'locked'])],
        ]);

        $actor = $request->user();
        $like = fn (string $v) => '%'.addcslashes($v, '%_\\').'%';

        $users = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', $like($t))->orWhere('email', 'like', $like($t))))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->when($filters['status'] ?? null, fn ($q, $s) => $s === 'locked'
                ? $q->where('locked_until', '>', now())
                : $q->where('status', $s))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'status' => $u->status->value,
                'roles' => $u->roles->pluck('name'),
                'two_factor' => $u->two_factor_confirmed_at !== null,
                'locked' => $u->isLocked(),
                'lock_reason' => $u->isLocked() ? $u->lock_reason : null,
                'last_login_at' => $u->last_login_at?->diffForHumans(),
                'can_manage' => $actor->can('manage', $u),
                'can_manage_password' => $actor->can('managePassword', $u),
                'can_assign_roles' => $actor->can('assignRoles', $u),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => (object) $filters,
            'roleOptions' => collect(RoleName::cases())->map(fn (RoleName $r) => ['value' => $r->value, 'label' => $r->label()]),
            'assignableRoles' => $this->roles->assignableBy($actor)->pluck('name'),
            'canCreate' => $actor->can('create', User::class),
        ]);
    }

    /**
     * Provision a staff, faculty or student account. No password is chosen
     * by the admin: the user sets their own through a reset link.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::password(40),
            ]);
            // Admin-provisioned addresses are institutional; ownership is
            // proven when the user follows the set-password link.
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles($data['roles']);

            return $user;
        });

        $this->audit->record('user.created', 'users', $user, null, ['email' => $user->email, 'roles' => $data['roles']]);
        Password::broker()->sendResetLink(['email' => $user->email]);

        return back()->with('success', "Account created. {$user->email} has been sent a link to set a password.");
    }

    public function updateStatus(Request $request, User $user, SessionManager $sessions): RedirectResponse
    {
        $this->authorize('manage', $user);

        $data = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)],
            'reason' => ['required_unless:status,active', 'nullable', 'string', 'max:500'],
        ]);

        $before = $user->status->value;
        $user->forceFill(['status' => $data['status']])->save();

        if ($user->status !== UserStatus::Active) {
            $sessions->revokeAll($user);
        }

        $this->audit->record('user.status_changed', 'users', $user, ['status' => $before], ['status' => $data['status'], 'reason' => $data['reason'] ?? null]);

        return back()->with('success', "{$user->name} is now {$user->status->label()}.");
    }

    /**
     * Correct a member's email address. The new address must be verified
     * again, the old address is told (in case the change was not expected),
     * and the change is audited. Never exposed publicly: email visibility is
     * still governed by the member's own privacy setting.
     */
    public function updateEmail(Request $request, User $user): RedirectResponse
    {
        $this->authorize('manage', $user);
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc,strict', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], ['email.unique' => 'Another account already uses this email address.']);

        $new = Str::lower(trim($data['email']));
        $old = $user->email;
        if ($new === $old) {
            return back()->with('status', 'No change.');
        }

        $user->forceFill(['email' => $new, 'email_verified_at' => null])->save();
        $user->sendEmailVerificationNotification();
        Notification::route('mail', $old)->notify(new EmailChangedByAdmin($new));
        $this->audit->record('user.email_changed', 'rbac', $user, ['email' => $old], ['email' => $new]);

        return back()->with('success', "Email updated. {$user->name} needs to verify the new address.");
    }

    /** Administrator sets a new password (policy rules, confirmed, hashed). */
    public function changePassword(Request $request, User $user, AdminPasswordService $passwords): RedirectResponse
    {
        $this->guardPassword($request, $user, AdminPasswordService::CHANGED, $passwords);

        try {
            $data = $request->validate([
                'password' => ['required', 'string', PasswordRule::default(), 'confirmed'],
                'require_change' => ['boolean'],
                'reason' => ['required', 'string', 'min:10', 'max:500'],
            ]);
        } catch (ValidationException $e) {
            // Which fields failed — never what was typed.
            $passwords->recordAttempt(AdminPasswordService::CHANGED, $request->user(), $user, 'failed', 'validation: '.implode(', ', array_keys($e->errors())), $request->string('reason')->limit(500)->toString() ?: null);
            throw $e;
        }

        $passwords->change($request->user(), $user, $data['password'], $data['require_change'] ?? true, $data['reason']);

        return back()->with('success', "Password changed for {$user->name}. They've been signed out everywhere and notified.");
    }

    /** Invalidate the current password and email the user a reset link. */
    public function resetPassword(Request $request, User $user, AdminPasswordService $passwords): RedirectResponse
    {
        $this->guardPassword($request, $user, AdminPasswordService::RESET, $passwords);

        try {
            $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        } catch (ValidationException $e) {
            $passwords->recordAttempt(AdminPasswordService::RESET, $request->user(), $user, 'failed', 'validation: reason');
            throw $e;
        }

        $passwords->reset($request->user(), $user, $data['reason']);

        return back()->with('success', "Password reset for {$user->name}. Their old password no longer works; a link to choose a new one has been emailed.");
    }

    /** Authorise (auditing refusals) before any password operation. */
    private function guardPassword(Request $request, User $user, string $action, AdminPasswordService $passwords): void
    {
        if ($request->user()->cannot('managePassword', $user)) {
            $passwords->recordAttempt($action, $request->user(), $user, 'denied', 'not authorised');
            abort(403);
        }
    }

    public function unlock(Request $request, User $user, AccountLockout $lockout): RedirectResponse
    {
        $this->authorize('manage', $user);

        $lockout->unlock($user, $request->user());

        return back()->with('success', "{$user->name} has been unlocked.");
    }

    /**
     * Lost-device recovery (SRS 14): clears the user's authenticator so they
     * re-enrol at next sign-in. Admins never see the secret itself.
     */
    public function resetTwoFactor(Request $request, User $user, SessionManager $sessions): RedirectResponse
    {
        $this->authorize('manage', $user);

        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $sessions->revokeAll($user);

        $this->audit->record('two_factor.reset_by_admin', 'security', $user, null, ['reason' => $data['reason']]);

        return back()->with('success', "Two-factor authentication reset for {$user->name}. They will be asked to enrol again.");
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $this->authorize('assignRoles', $user);

        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($this->roles->assignableBy($request->user())->pluck('name'))],
        ]);

        $this->roles->sync($request->user(), $user, $data['roles'] ?? []);

        return back()->with('success', "Roles updated for {$user->name}.");
    }
}
