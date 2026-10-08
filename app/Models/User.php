<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/*
 | Only self-service fields are mass-assignable. Status, lockout and login
 | metadata are set explicitly by the services that own them (SRS 75).
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'password_change_required' => 'boolean',
            'status' => UserStatus::class,
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function alumniProfile(): HasOne
    {
        // chaperone(): the loaded profile gets this user as its `user`
        // relation, so profile->user never triggers another query.
        return $this->hasOne(AlumniProfile::class)->chaperone();
    }

    public function speakerProfile(): HasOne
    {
        return $this->hasOne(SpeakerProfile::class);
    }

    public function mentorProfile(): HasOne
    {
        return $this->hasOne(MentorProfile::class);
    }

    public function communityMemberships(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function sentConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'requester_id');
    }

    public function receivedConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'addressee_id');
    }

    /** Users this user has blocked. */
    public function blocks(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_blocks', 'blocker_id', 'blocked_id');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followed_id');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /** Whether the user holds a role that the 2FA policy makes mandatory. */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(config('security.two_factor_required_roles'));
    }

    /** Privileged users get stricter session limits (SRS 16, 78). */
    public function isPrivileged(): bool
    {
        return $this->requiresTwoFactor();
    }

    /**
     * Part of the IIITM network: verified alumni, students, faculty and
     * staff. Unverified registrations are not, which keeps member-only
     * spaces closed to anyone who merely signs up.
     */
    public function isCommunityMember(): bool
    {
        return $this->alumniProfile?->isVerified()
            || $this->hasAnyRole([RoleName::Student->value, RoleName::Faculty->value])
            || $this->can(Permission::AlumniView->value);
    }

    public function canAccessAdmin(): bool
    {
        return $this->hasAnyPermission(array_map(
            fn (Permission $p) => $p->value,
            Permission::adminPanel(),
        ));
    }
}
