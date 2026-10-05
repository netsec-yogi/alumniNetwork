<?php

namespace Tests\Feature\Career;

use App\Enums\RoleName;
use App\Models\EngagementActivity;
use App\Models\JobPosting;
use App\Models\JobReferralRequest;
use App\Models\Report;
use App\Notifications\JobPendingModeration;
use App\Notifications\ReferralResponded;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class JobTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'job', 'title' => 'Backend Engineer', 'organization' => 'Razorpay', 'location' => 'Bengaluru',
            'work_mode' => 'hybrid', 'employment_type' => 'full_time', 'skills' => ['Go', 'Postgres'],
            'description' => str_repeat('Build payment systems at scale. ', 3),
            'apply_url' => 'https://razorpay.com/jobs/1', 'deadline' => now()->addWeek()->toDateString(),
            'referral_available' => true,
        ], $overrides);
    }

    public function test_alumni_posts_go_to_moderation_and_moderators_are_notified(): void
    {
        Notification::fake();
        $career = $this->admin(RoleName::CareerAdmin);
        $alumnus = $this->verifiedAlumnus()->user;

        $this->actingAs($alumnus)->post(route('jobs.store'), $this->payload(['status' => 'approved']))->assertRedirect();

        $job = JobPosting::firstOrFail();
        $this->assertSame(JobPosting::PENDING, $job->status);
        Notification::assertSentTo($career, JobPendingModeration::class);

        // Not listed until approved.
        $student = $this->user(RoleName::Student);
        $this->actingAs($student)->get(route('jobs.index'))->assertInertia(fn (Assert $p) => $p->where('jobs.total', 0));
        $this->actingAs($student)->get(route('jobs.show', $job))->assertForbidden();

        $this->actingAs($career)->post(route('admin.jobs.approve', $job))->assertSessionHas('success');
        $this->actingAs($student)->get(route('jobs.index'))->assertInertia(fn (Assert $p) => $p->where('jobs.total', 1));
        $this->assertSame(1, EngagementActivity::where('activity_type', 'JOB_POSTED')->count());
    }

    public function test_career_staff_posts_go_live_immediately(): void
    {
        $this->actingAs($this->admin(RoleName::CareerAdmin))->post(route('jobs.store'), $this->payload());

        $this->assertSame(JobPosting::APPROVED, JobPosting::firstOrFail()->status);
    }

    public function test_students_and_unverified_alumni_cannot_post(): void
    {
        $this->actingAs($this->user(RoleName::Student))->post(route('jobs.store'), $this->payload())->assertForbidden();
        $this->actingAs($this->verifiedAlumnus(['verification_status' => 'pending'])->user)->post(route('jobs.store'), $this->payload())->assertForbidden();
    }

    public function test_editing_a_live_post_sends_it_back_for_review(): void
    {
        Notification::fake();
        $alumnus = $this->verifiedAlumnus()->user;
        $job = JobPosting::factory()->create(['posted_by' => $alumnus->id]);

        $this->actingAs($alumnus)->put(route('jobs.update', $job), $this->payload(['apply_url' => 'https://phish.example.com']))->assertRedirect();

        $this->assertSame(JobPosting::PENDING, $job->fresh()->status);
    }

    public function test_others_cannot_edit_or_close_a_post(): void
    {
        $job = JobPosting::factory()->create();
        $other = $this->verifiedAlumnus()->user;

        $this->actingAs($other)->put(route('jobs.update', $job), $this->payload())->assertForbidden();
        $this->actingAs($other)->post(route('jobs.close', $job))->assertForbidden();
    }

    public function test_application_links_must_be_https(): void
    {
        $this->actingAs($this->verifiedAlumnus()->user)
            ->post(route('jobs.store'), $this->payload(['apply_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('apply_url');
    }

    public function test_expired_posts_drop_out_and_are_closed_by_the_scheduler(): void
    {
        $job = JobPosting::factory()->create(['deadline' => now()->subDay()->toDateString()]);

        $this->actingAs($this->user(RoleName::Student))->get(route('jobs.index'))->assertInertia(fn (Assert $p) => $p->where('jobs.total', 0));
        $this->artisan('jobs:close-expired')->assertSuccessful();
        $this->assertSame(JobPosting::CLOSED, $job->fresh()->status);
    }

    public function test_referral_flow_is_decided_by_the_poster(): void
    {
        Notification::fake();
        $poster = $this->verifiedAlumnus()->user;
        $job = JobPosting::factory()->create(['posted_by' => $poster->id]);
        $student = $this->user(RoleName::Student);

        $this->actingAs($student)->post(route('jobs.referrals.store', $job), ['message' => str_repeat('I have shipped Go services. ', 2)])->assertSessionHas('success');
        $this->actingAs($student)->post(route('jobs.referrals.store', $job), ['message' => str_repeat('Asking again, please! ', 2)])->assertSessionHas('error');

        $referral = JobReferralRequest::firstOrFail();
        // Only the poster may decide.
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('jobs.referrals.respond', $referral), ['decision' => 'accept'])->assertSessionHas('error');
        $this->actingAs($poster)->post(route('jobs.referrals.respond', $referral), ['decision' => 'accept', 'note' => 'Send me your CV.'])->assertSessionHas('success');

        $this->assertSame(JobReferralRequest::ACCEPTED, $referral->fresh()->status);
        Notification::assertSentTo($student, ReferralResponded::class);
        $this->assertSame(1, EngagementActivity::where('activity_type', 'JOB_REFERRAL')->count());
    }

    public function test_cannot_request_referral_on_own_or_non_referral_post(): void
    {
        $poster = $this->verifiedAlumnus()->user;
        $own = JobPosting::factory()->create(['posted_by' => $poster->id]);
        $noReferral = JobPosting::factory()->create(['referral_available' => false]);
        $msg = ['message' => str_repeat('Please consider me for this. ', 2)];

        $this->actingAs($poster)->post(route('jobs.referrals.store', $own), $msg)->assertForbidden();
        $this->actingAs($poster)->post(route('jobs.referrals.store', $noReferral), $msg)->assertForbidden();
    }

    public function test_reported_job_can_be_removed_by_career_moderator(): void
    {
        $job = JobPosting::factory()->create();
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('reports.store'), ['type' => 'job_posting', 'id' => $job->id, 'reason' => 'spam']);

        $career = $this->admin(RoleName::CareerAdmin);
        $report = Report::firstOrFail();
        $this->actingAs($career)->post(route('admin.moderation.resolve', $report), ['action' => 'remove', 'resolution' => 'Fake recruiter.'])->assertSessionHas('success');

        $this->assertSoftDeleted($job);
    }
}
