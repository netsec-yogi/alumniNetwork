<?php

namespace Tests\Feature\Alumni;

use App\Enums\RoleName;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    /** SRS 104, test 1. */
    public function test_private_fields_are_not_sent_to_other_users(): void
    {
        $owner = $this->verifiedAlumnus([
            'company' => 'Secret Corp',
            'visibility' => ['company' => 'private', 'email' => 'private', 'phone' => 'private'],
        ]);
        $viewer = $this->verifiedAlumnus()->user;

        $this->actingAs($viewer)->get(route('alumni.show', $owner))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Directory/Show')
                ->missing('profile.company')
                ->missing('profile.email')
                ->missing('profile.phone')
                ->has('profile.designation'));
    }

    public function test_owner_sees_their_own_private_fields(): void
    {
        $owner = $this->verifiedAlumnus(['company' => 'Secret Corp', 'visibility' => ['company' => 'private']]);

        $this->actingAs($owner->user)->get(route('alumni.show', $owner))
            ->assertInertia(fn (Assert $page) => $page->where('profile.company', 'Secret Corp'));
    }

    public function test_alumni_only_fields_are_hidden_from_students(): void
    {
        $owner = $this->verifiedAlumnus(['company' => 'Acme']); // company defaults to alumni-only
        $student = $this->user(RoleName::Student);

        $this->actingAs($student)->get(route('alumni.show', $owner))
            ->assertInertia(fn (Assert $page) => $page->missing('profile.company'));
    }

    public function test_directory_filters_cannot_reveal_hidden_fields(): void
    {
        $this->verifiedAlumnus(['company' => 'Hidden Ventures', 'visibility' => ['company' => 'private']]);
        $this->verifiedAlumnus(['company' => 'Hidden Ventures']);
        $viewer = $this->verifiedAlumnus()->user;

        $this->actingAs($viewer)->get(route('directory', ['company' => 'Hidden Ventures']))
            ->assertInertia(fn (Assert $page) => $page->where('profiles.total', 1));
    }

    public function test_unverified_alumni_cannot_browse_the_directory(): void
    {
        $pending = $this->verifiedAlumnus(['verification_status' => 'pending']);
        $someone = $this->verifiedAlumnus();

        $this->actingAs($pending->user)->get(route('directory'))->assertForbidden();
        $this->actingAs($pending->user)->get(route('alumni.show', $someone))->assertForbidden();
    }

    public function test_unverified_profiles_are_not_visible(): void
    {
        $pending = $this->verifiedAlumnus(['verification_status' => 'pending']);

        $this->actingAs($this->verifiedAlumnus()->user)->get(route('alumni.show', $pending))->assertForbidden();
    }
}
