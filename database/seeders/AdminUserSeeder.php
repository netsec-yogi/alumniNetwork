<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The first Super Administrator, from SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD.
 * 2FA enrolment is forced on first sign-in by EnsureTwoFactorEnrolled.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            throw new RuntimeException('Set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD to seed the first administrator.');
        }

        if (app()->isProduction() && strlen($password) < 16) {
            throw new RuntimeException('Use a SEED_ADMIN_PASSWORD of at least 16 characters in production.');
        }

        $admin = User::firstOrNew(['email' => strtolower($email)]);

        if (! $admin->exists) {
            $admin->fill(['name' => 'Portal Administrator', 'password' => $password]);
            $admin->forceFill([
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'status' => UserStatus::Active,
            ])->save();
        }

        $admin->assignRole(RoleName::SuperAdmin->value);
    }
}
