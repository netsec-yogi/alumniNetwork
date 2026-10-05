<?php

namespace Tests;

use App\Enums\RoleName;
use App\Models\AlumniProfile;
use App\Models\User;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, AcademicStructureSeeder::class]);
    }

    protected function user(RoleName $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    /** A privileged user who has already enrolled in 2FA. */
    protected function admin(RoleName $role = RoleName::SuperAdmin, array $attributes = []): User
    {
        return User::factory()->withTwoFactor()->role($role)->create($attributes);
    }

    protected function verifiedAlumnus(array $profile = []): AlumniProfile
    {
        return AlumniProfile::factory()->create($profile);
    }

    protected function totp(string $secret = 'JBSWY3DPEHPK3PXP'): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }
}
