<?php

namespace Tests\Feature\Network;

use App\Enums\RoleName;
use App\Models\EngagementActivity;
use App\Models\ResearchOpportunity;
use App\Models\SpeakerInvitation;
use App\Models\SpeakerProfile;
use App\Models\Startup;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NetworkModulesTest extends TestCase
{
    private function startupPayload(array $o = []): array
    {
        return array_merge(['name' => 'PayFlow', 'description' => str_repeat('Payments infrastructure for SMEs. ', 2), 'industry' => 'Fintech', 'funding_stage' => 'seed', 'is_hiring' => true, 'my_role' => 'CEO'], $o);
    }

    public function test_founder_creates_startup_with_verified_cofounders_only(): void
    {
        $founder = $this->verifiedAlumnus();
        $co = $this->verifiedAlumnus();
        $pending = $this->verifiedAlumnus(['verification_status' => 'pending']);

        $this->actingAs($founder->user)->post(route('startups.store'), $this->startupPayload(['cofounders' => $pending->roll_number]))->assertSessionHasErrors('cofounders');
        $this->actingAs($founder->user)->post(route('startups.store'), $this->startupPayload(['cofounders' => strtolower($co->roll_number)]))->assertRedirect();

        $startup = Startup::sole();
        $this->assertEqualsCanonicalizing([$founder->id, $co->id], $startup->founders->pluck('id')->all());
        $this->actingAs($this->user(RoleName::Student))->get(route('startups.index'))->assertInertia(fn (Assert $p) => $p->where('startups.total', 1));
    }

    public function test_only_founders_edit_and_content_managers_can_hide_without_joining(): void
    {
        $founder = $this->verifiedAlumnus();
        $this->actingAs($founder->user)->post(route('startups.store'), $this->startupPayload());
        $startup = Startup::sole();

        $this->actingAs($this->verifiedAlumnus()->user)->post(route('startups.update', $startup), $this->startupPayload(['name' => 'Hijacked']))->assertForbidden();

        $admin = $this->admin(RoleName::AlumniAdmin);
        $this->actingAs($admin)->post(route('startups.update', $startup), $this->startupPayload(['name' => 'PayFlow Labs']))->assertRedirect();
        $this->assertSame(1, $startup->fresh()->founders()->count(), 'editor is not added as a founder');

        $this->actingAs($admin)->post(route('startups.visibility', $startup));
        $this->actingAs($this->user(RoleName::Student))->get(route('startups.index'))->assertInertia(fn (Assert $p) => $p->where('startups.total', 0));
        $this->actingAs($this->user(RoleName::Student))->get(route('startups.show', $startup))->assertNotFound();
    }

    public function test_research_interest_reaches_only_the_poster(): void
    {
        $faculty = $this->user(RoleName::Faculty);
        $this->actingAs($faculty)->post(route('research.store'), ['type' => 'project', 'title' => 'Federated learning on edge devices', 'description' => str_repeat('Looking for industry partners. ', 2), 'areas' => ['ML']])->assertRedirect();
        $o = ResearchOpportunity::sole();

        $this->actingAs($this->user(RoleName::Student))->post(route('research.store'), ['type' => 'project', 'title' => 'x', 'description' => str_repeat('y', 40)])->assertForbidden();

        $alumnus = $this->verifiedAlumnus()->user;
        $this->actingAs($alumnus)->post(route('research.interest', $o), ['note' => 'I lead edge ML at a chip company and can help.'])->assertSessionHas('success');

        $this->actingAs($faculty)->get(route('research.show', $o))->assertInertia(fn (Assert $p) => $p->where('interests.0.email', $alumnus->email));
        $this->actingAs($this->verifiedAlumnus()->user)->get(route('research.show', $o))->assertInertia(fn (Assert $p) => $p->where('interests', []));
    }

    public function test_speaker_invitation_delivery_records_guest_lecture(): void
    {
        $speaker = $this->verifiedAlumnus()->user;
        $this->actingAs($speaker)->put(route('speakers.profile.update'), ['topics' => ['LLMs'], 'formats' => ['talk', 'workshop'], 'is_available' => true, 'remote' => true, 'in_person' => false])->assertRedirect();
        $faculty = $this->user(RoleName::Faculty);

        $this->actingAs($faculty)->get(route('speakers.index', ['topic' => 'llm']))->assertInertia(fn (Assert $p) => $p->where('speakers.total', 1));
        $this->actingAs($faculty)->post(route('speakers.invite', $speaker), ['title' => 'Guest lecture: LLMs in production', 'details' => str_repeat('Final-year students, 60 minutes. ', 2), 'format' => 'panel'])->assertSessionHasErrors('format');
        $this->actingAs($faculty)->post(route('speakers.invite', $speaker), ['title' => 'Guest lecture: LLMs in production', 'details' => str_repeat('Final-year students, 60 minutes. ', 2), 'format' => 'talk'])->assertSessionHas('success');

        $invitation = SpeakerInvitation::sole();
        $this->actingAs($faculty)->post(route('speakers.delivered', $invitation))->assertForbidden(); // not accepted yet
        $this->actingAs($speaker)->post(route('speakers.respond', $invitation), ['decision' => 'accept']);
        $this->actingAs($speaker)->post(route('speakers.delivered', $invitation))->assertForbidden(); // only the organiser confirms
        $this->actingAs($faculty)->post(route('speakers.delivered', $invitation))->assertSessionHas('success');

        $this->assertSame(1, EngagementActivity::where('activity_type', 'GUEST_LECTURE')->count());
    }

    public function test_unavailable_and_blocking_speakers_cannot_be_invited(): void
    {
        $speaker = $this->verifiedAlumnus()->user;
        SpeakerProfile::factory()->create(['user_id' => $speaker->id, 'is_available' => false]);
        $payload = ['title' => 'Talk', 'details' => str_repeat('details ', 6), 'format' => 'talk'];

        $this->actingAs($this->user(RoleName::Faculty))->post(route('speakers.invite', $speaker), $payload)->assertForbidden();
    }
}
