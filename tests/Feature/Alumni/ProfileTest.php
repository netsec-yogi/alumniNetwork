<?php

namespace Tests\Feature\Alumni;

use App\Models\AuditLog;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    /** SRS 75 & 104 test 3: only your own profile, only self-service fields. */
    public function test_profile_update_ignores_protected_fields(): void
    {
        $profile = $this->verifiedAlumnus(['verification_status' => 'pending', 'roll_number' => 'ORIGINAL']);

        $this->actingAs($profile->user)->put(route('profile.update'), [
            'company' => 'New Co',
            'verification_status' => 'verified',
            'roll_number' => 'FORGED',
            'user_id' => 999,
        ])->assertSessionHasNoErrors();

        $fresh = $profile->fresh();
        $this->assertSame('New Co', $fresh->company);
        $this->assertSame('pending', $fresh->verification_status->value);
        $this->assertSame('ORIGINAL', $fresh->roll_number);
        $this->assertSame($profile->user_id, $fresh->user_id);
    }

    public function test_profile_links_must_be_https(): void
    {
        $profile = $this->verifiedAlumnus();

        $this->actingAs($profile->user)->put(route('profile.update'), [
            'website_url' => 'javascript:alert(1)',
            'linkedin_url' => 'https://evil.example.com/in/me',
        ])->assertSessionHasErrors(['website_url', 'linkedin_url']);
    }

    public function test_changing_email_requires_current_password_and_reverification(): void
    {
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->put(route('profile.account.update'), ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put(route('profile.account.update'), [
            'name' => $user->name, 'email' => 'new@example.com', 'current_password' => 'password',
        ])->assertRedirect(route('verification.notice'));

        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_profile_changes_are_audited_with_only_changed_fields(): void
    {
        $profile = $this->verifiedAlumnus(['city' => 'Pune']);

        $this->actingAs($profile->user)->put(route('profile.update'), ['city' => 'Gwalior']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'profile.updated', 'entity_id' => $profile->id]);
        $log = AuditLog::where('action', 'profile.updated')->first();
        $this->assertSame(['city' => 'Pune'], array_intersect_key($log->old_values, ['city' => 1]));
    }
}
