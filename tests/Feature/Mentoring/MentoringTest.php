<?php

namespace Tests\Feature\Mentoring;

use App\Enums\RoleName;
use App\Models\EngagementActivity;
use App\Models\MentorProfile;
use App\Models\MentorshipRequest;
use App\Services\MentorMatchingService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MentoringTest extends TestCase
{
    private function mentor(array $profile = [], array $mentor = [])
    {
        $alumnus = $this->verifiedAlumnus($profile);
        MentorProfile::factory()->create(['user_id' => $alumnus->user_id, ...$mentor]);

        return $alumnus->user->fresh();
    }

    public function test_matching_ranks_by_configured_weights(): void
    {
        config(['mentoring.weights' => ['programme' => 0, 'skills' => 50, 'career_interest' => 50, 'industry' => 0, 'experience' => 0, 'mentor_preference' => 0, 'location' => 0, 'availability' => 0]]);
        $strong = $this->mentor([], ['categories' => ['research'], 'expertise' => ['ML', 'NLP']]);
        $weak = $this->mentor([], ['categories' => ['career'], 'expertise' => ['Sales']]);
        $student = $this->user(RoleName::Student);

        $results = app(MentorMatchingService::class)->match($student, ['category' => 'research', 'interests' => ['ml', 'nlp']]);

        $this->assertSame($strong->id, $results->first()['mentor']->user_id);
        $this->assertSame(100, $results->first()['score']);
        $this->assertSame(0, $results->firstWhere('mentor.user_id', $weak->id)['score']);
    }

    public function test_full_paused_and_mismatched_mentors_are_not_suggested(): void
    {
        $full = $this->mentor([], ['max_mentees' => 1]);
        MentorshipRequest::forceCreate(['mentor_id' => $full->id, 'mentee_id' => $this->user(RoleName::Student)->id, 'category' => 'career', 'goals' => 'x', 'status' => 'accepted']);
        $this->mentor([], ['is_accepting' => false]);
        $this->mentor([], ['preferred_mentee' => 'alumni']);
        $open = $this->mentor();

        $ids = app(MentorMatchingService::class)->match($this->user(RoleName::Student), ['category' => 'career'])->pluck('mentor.user_id');

        $this->assertEquals([$open->id], $ids->all());
    }

    public function test_hidden_location_never_affects_the_score(): void
    {
        config(['mentoring.weights' => ['programme' => 0, 'skills' => 0, 'career_interest' => 0, 'industry' => 0, 'experience' => 0, 'mentor_preference' => 0, 'location' => 100, 'availability' => 0]]);
        $this->mentor(['city' => 'Pune', 'visibility' => ['location' => 'private']]);

        $result = app(MentorMatchingService::class)->match($this->verifiedAlumnus()->user, ['location' => 'Pune'])->first();

        $this->assertSame(0, $result['score']);
    }

    public function test_request_accept_complete_flow(): void
    {
        Notification::fake();
        $mentor = $this->mentor();
        $student = $this->user(RoleName::Student);

        $this->actingAs($student)->post(route('mentoring.store', $mentor), ['category' => 'career', 'goals' => str_repeat('Help me prepare for interviews. ', 2)])->assertSessionHas('success');
        $this->actingAs($student)->post(route('mentoring.store', $mentor), ['category' => 'career', 'goals' => str_repeat('Asking a second time here. ', 2)])->assertSessionHas('error');

        $m = MentorshipRequest::firstOrFail();
        $this->actingAs($student)->post(route('mentoring.respond', $m), ['decision' => 'accept'])->assertSessionHas('error');
        $this->actingAs($mentor)->post(route('mentoring.respond', $m), ['decision' => 'accept'])->assertSessionHas('success');

        // Contact details are exchanged only after acceptance.
        $this->actingAs($student)->get(route('mentoring.index'))->assertInertia(fn (Assert $p) => $p->where('asMentee.0.contact', $mentor->email));

        $this->actingAs($student)->post(route('mentoring.complete', $m))->assertSessionHas('success');
        $this->assertSame(MentorshipRequest::COMPLETED, $m->fresh()->status);
        $this->assertSame(1, EngagementActivity::where('activity_type', 'MENTORSHIP_COMPLETED')->count());
    }

    public function test_mentor_cannot_accept_beyond_capacity(): void
    {
        Notification::fake();
        $mentor = $this->mentor([], ['max_mentees' => 1]);
        [$s1, $s2] = [$this->user(RoleName::Student), $this->user(RoleName::Student)];
        $goals = ['category' => 'career', 'goals' => str_repeat('I would value your guidance. ', 2)];

        $this->actingAs($s1)->post(route('mentoring.store', $mentor), $goals);
        $this->actingAs($s2)->post(route('mentoring.store', $mentor), $goals);
        [$r1, $r2] = MentorshipRequest::orderBy('id')->get();

        $this->actingAs($mentor)->post(route('mentoring.respond', $r1), ['decision' => 'accept'])->assertSessionHas('success');
        $this->actingAs($mentor)->post(route('mentoring.respond', $r2), ['decision' => 'accept'])->assertSessionHas('error');
    }

    public function test_only_verified_alumni_and_faculty_can_become_mentors(): void
    {
        $payload = ['categories' => ['career'], 'preferred_mentee' => 'both', 'max_mentees' => 2, 'preferred_mode' => 'video', 'is_accepting' => true];

        $this->actingAs($this->user(RoleName::Student))->put(route('mentoring.profile.update'), $payload)->assertForbidden();
        $this->actingAs($this->verifiedAlumnus(['verification_status' => 'pending'])->user)->put(route('mentoring.profile.update'), $payload)->assertForbidden();

        $alumnus = $this->verifiedAlumnus();
        $this->actingAs($alumnus->user)->put(route('mentoring.profile.update'), $payload)->assertRedirect();
        $this->assertContains('mentor', $alumnus->fresh()->interests);
    }

    public function test_blocked_users_cannot_request_mentoring(): void
    {
        $mentor = $this->mentor();
        $student = $this->user(RoleName::Student);
        $mentor->blocks()->attach($student->id);

        $this->actingAs($student)->post(route('mentoring.store', $mentor), ['category' => 'career', 'goals' => str_repeat('Please help me with this. ', 2)])->assertSessionHas('error');
    }
}
