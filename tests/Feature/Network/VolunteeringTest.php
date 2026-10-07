<?php

namespace Tests\Feature\Network;

use App\Enums\RoleName;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EngagementActivity;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerSignup;
use Tests\TestCase;

class VolunteeringTest extends TestCase
{
    private function opportunity(array $o = []): VolunteerOpportunity
    {
        $op = new VolunteerOpportunity(array_merge(['category' => 'admissions', 'title' => 'JEE counselling helpdesk', 'description' => 'Help applicants choose programmes.'], $o));
        $op->forceFill(['created_by' => $this->admin(RoleName::AlumniAdmin)->id, 'status' => 'open'])->save();

        return $op;
    }

    public function test_sign_up_log_hours_approve_records_engagement(): void
    {
        $o = $this->opportunity();
        $alumnus = $this->verifiedAlumnus()->user;

        $this->actingAs($alumnus)->post(route('volunteering.sign-up', $o))->assertSessionHas('success');
        $signup = VolunteerSignup::sole();
        $this->actingAs($alumnus)->post(route('volunteering.hours', $signup), ['hours' => 3.3, 'outcome' => 'Answered 40 parent queries over two days.']);
        $this->assertSame(3.5, $signup->fresh()->hours, 'hours are rounded to half hours');

        // Volunteers can't approve themselves.
        $this->actingAs($alumnus)->post(route('volunteering.review', $signup), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->admin(RoleName::AlumniAdmin))->post(route('volunteering.review', $signup), ['decision' => 'approve', 'hours' => 3])->assertSessionHas('success');

        $activity = EngagementActivity::where('activity_type', 'VOLUNTEERED')->sole();
        $this->assertSame(3, (int) $activity->metadata['hours']);
        $this->assertSame('volunteer', $activity->engagement_mode);
    }

    public function test_slots_are_enforced(): void
    {
        $o = $this->opportunity(['slots' => 1]);
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('volunteering.sign-up', $o))->assertSessionHas('success');
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('volunteering.sign-up', $o))->assertSessionHas('error', 'All slots are taken.');
    }

    public function test_group_admins_organise_only_for_their_group(): void
    {
        $mine = Community::factory()->chapter('Pune')->create();
        $other = Community::factory()->chapter('Delhi')->create();
        $lead = $this->verifiedAlumnus()->user;
        CommunityMember::forceCreate(['community_id' => $mine->id, 'user_id' => $lead->id, 'role' => 'admin', 'status' => 'active']);
        $payload = fn ($cid) => ['category' => 'events', 'title' => 'Meetup crew', 'description' => 'Help run the Pune chapter meetup.', 'community_id' => $cid];

        $this->actingAs($lead)->post(route('volunteering.store'), $payload($other->id))->assertSessionHasErrors('community_id');
        $this->actingAs($lead)->post(route('volunteering.store'), $payload($mine->id))->assertSessionHas('success');
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('volunteering.store'), $payload($mine->id))->assertForbidden();
    }

    public function test_students_cannot_volunteer_and_pending_hours_need_review(): void
    {
        $o = $this->opportunity();
        $this->actingAs($this->user(RoleName::Student))->post(route('volunteering.sign-up', $o))->assertForbidden();

        $alumnus = $this->verifiedAlumnus()->user;
        $this->actingAs($alumnus)->post(route('volunteering.sign-up', $o));
        $this->actingAs($this->admin(RoleName::AlumniAdmin))->post(route('volunteering.review', VolunteerSignup::sole()), ['decision' => 'approve'])->assertStatus(409);
    }
}
