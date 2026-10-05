<?php

namespace App\Actions\Fortify;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Consent;
use App\Models\Programme;
use App\Models\User;
use App\Services\AlumniVerificationService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Alumni self-registration (SRS 19-20).
 *
 * Creates the account, the alumni profile and the consent records in one
 * transaction, then submits the profile for verification. Students, faculty
 * and staff are provisioned by administrators (or SSO later), not here.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public const TERMS_VERSION = '2026-10';

    public function __construct(
        private readonly AlumniVerificationService $verification,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $currentYear = (int) now()->year;

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'roll_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
            'programme_id' => ['required', 'integer', Rule::exists(Programme::class, 'id')->where('is_active', true)],
            'admission_year' => ['nullable', 'integer', 'min:1997', 'max:'.$currentYear],
            'graduation_year' => ['required', 'integer', 'min:1998', 'max:'.($currentYear + 1), 'gte:admission_year'],
            'accept_terms' => ['accepted'],
            'accept_communications' => ['boolean'],
        ], [
            'accept_terms.accepted' => 'Please accept the terms of use and privacy policy to continue.',
        ])->validate();

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->forceFill(['status' => UserStatus::Active, 'password_changed_at' => now()])->save();
            $user->assignRole(RoleName::Alumni->value);

            $profile = $user->alumniProfile()->make();
            $profile->forceFill([
                'roll_number' => $this->verification->normaliseRoll($data['roll_number']),
                'programme_id' => $data['programme_id'],
                'admission_year' => $data['admission_year'] ?? null,
                'graduation_year' => $data['graduation_year'],
            ])->save();

            $this->recordConsents($user, (bool) ($data['accept_communications'] ?? false));
            $this->audit->record('user.registered', 'auth', $user, null, ['email' => $user->email], $user);

            $this->verification->submit($profile->setRelation('user', $user));

            return $user;
        });
    }

    private function recordConsents(User $user, bool $communications): void
    {
        $context = ['ip_address' => request()->ip(), 'user_agent' => substr((string) request()->userAgent(), 0, 500)];

        foreach ([Consent::TERMS, Consent::PRIVACY] as $type) {
            $user->consents()->create(['consent_type' => $type, 'version' => self::TERMS_VERSION, 'granted' => true] + $context);
        }

        $user->consents()->create([
            'consent_type' => Consent::COMMUNICATIONS,
            'version' => self::TERMS_VERSION,
            'granted' => $communications,
        ] + $context);
    }
}
