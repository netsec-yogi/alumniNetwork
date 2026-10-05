<?php

namespace Tests\Feature\Auth;

use App\Enums\VerificationStatus;
use App\Models\AlumniRecord;
use App\Models\Consent;
use App\Models\Programme;
use App\Models\User;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ananya Sharma',
            'email' => 'ananya@example.com',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
            'roll_number' => '2015ipg-045',
            'programme_id' => Programme::where('code', 'IPG-MTECH')->value('id'),
            'admission_year' => 2015,
            'graduation_year' => 2020,
            'accept_terms' => true,
            'accept_communications' => false,
        ], $overrides);
    }

    public function test_matching_institute_record_verifies_automatically(): void
    {
        AlumniRecord::create([
            'roll_number' => '2015IPG-045',
            'name' => 'Sharma Ananya',
            'programme_id' => Programme::where('code', 'IPG-MTECH')->value('id'),
            'graduation_year' => 2020,
        ]);

        $this->post('/register', $this->payload())->assertRedirect();

        $profile = User::where('email', 'ananya@example.com')->first()->alumniProfile;
        $this->assertSame(VerificationStatus::Verified, $profile->verification_status);
        $this->assertSame('2015IPG-045', $profile->roll_number, 'roll numbers are normalised');
        $this->assertNotNull($profile->alumni_record_id);
    }

    public function test_no_matching_record_goes_to_manual_review(): void
    {
        $this->post('/register', $this->payload())->assertRedirect();

        $profile = User::where('email', 'ananya@example.com')->first()->alumniProfile;
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        $this->assertDatabaseHas('verification_requests', ['alumni_profile_id' => $profile->id, 'method' => 'manual', 'status' => 'pending']);
    }

    public function test_a_claimed_record_cannot_verify_a_second_account(): void
    {
        $record = AlumniRecord::create([
            'roll_number' => '2015IPG-045', 'name' => 'Ananya Sharma',
            'programme_id' => Programme::where('code', 'IPG-MTECH')->value('id'), 'graduation_year' => 2020,
        ]);
        $this->verifiedAlumnus(['alumni_record_id' => $record->id, 'roll_number' => '2015IPG-045']);

        $this->post('/register', $this->payload());

        $this->assertSame(VerificationStatus::Pending, User::where('email', 'ananya@example.com')->first()->alumniProfile->verification_status);
    }

    public function test_consents_are_recorded_with_version(): void
    {
        $this->post('/register', $this->payload());

        $user = User::where('email', 'ananya@example.com')->first();
        $this->assertSame(3, $user->consents()->count());
        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'consent_type' => Consent::COMMUNICATIONS, 'granted' => false]);
    }

    public function test_terms_must_be_accepted_and_passwords_must_be_long(): void
    {
        $this->post('/register', $this->payload(['accept_terms' => false, 'password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors(['accept_terms', 'password']);

        $this->assertDatabaseMissing('users', ['email' => 'ananya@example.com']);
    }

    public function test_registration_cannot_set_its_own_verification_status_or_role(): void
    {
        $this->post('/register', $this->payload(['verification_status' => 'verified', 'roles' => ['super_admin'], 'status' => 'active']));

        $user = User::where('email', 'ananya@example.com')->first();
        $this->assertSame(VerificationStatus::Pending, $user->alumniProfile->verification_status);
        $this->assertSame(['alumni'], $user->getRoleNames()->all());
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/register', $this->payload(['email' => "user{$i}@example.com", 'roll_number' => "R{$i}"]));
            auth()->logout();
        }

        $this->post('/register', $this->payload(['email' => 'one-more@example.com']))->assertStatus(429);
    }
}
