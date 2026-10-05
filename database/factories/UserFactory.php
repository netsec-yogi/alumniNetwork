<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'password_changed_at' => now(),
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(RoleName $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role->value));
    }

    /** A confirmed TOTP enrolment with a known secret, for 2FA tests. */
    public function withTwoFactor(string $secret = 'JBSWY3DPEHPK3PXP', array $recoveryCodes = ['code-one-aaaa', 'code-two-bbbb']): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
