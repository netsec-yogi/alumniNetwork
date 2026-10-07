<?php

namespace Tests\Feature\Surveys;

use App\Enums\RoleName;
use App\Models\EngagementActivity;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Survey;
use App\Services\SurveyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SurveyTest extends TestCase
{
    private function create(array $o = []): Survey
    {
        $admin = $this->admin(RoleName::AlumniAdmin);
        $this->actingAs($admin)->post(route('admin.surveys.store'), array_merge([
            'title' => 'Alumni pulse 2026', 'is_anonymous' => true, 'audience' => ['roles' => ['alumni']],
            'questions' => [
                ['type' => 'single', 'prompt' => 'Which event format do you prefer?', 'options' => ['In person', 'Online'], 'required' => true],
                ['type' => 'nps', 'prompt' => 'Recommend?', 'required' => true],
                ['type' => 'text', 'prompt' => 'Anything else?', 'required' => false],
            ],
        ], $o))->assertRedirect();
        $survey = Survey::latest('id')->first();
        Notification::fake();
        app(SurveyService::class)->publish($survey);

        return $survey->fresh('questions');
    }

    public function test_anonymous_responses_store_no_user_and_block_repeats(): void
    {
        $alumnus = $this->verifiedAlumnus()->user;
        $survey = $this->create();
        [$q1, $q2] = $survey->questions;

        $this->actingAs($alumnus)->post(route('surveys.submit', $survey), ['answers' => [$q1->id => 'Online', $q2->id => 9]])->assertSessionHas('success');
        $this->actingAs($alumnus)->post(route('surveys.submit', $survey), ['answers' => [$q1->id => 'Online', $q2->id => 9]])->assertSessionHas('error');

        $this->assertNull(DB::table('survey_responses')->value('user_id'));
        $this->assertSame(1, EngagementActivity::where('activity_type', 'SURVEY_RESPONSE')->count());
    }

    public function test_answers_are_validated_by_question_type(): void
    {
        $alumnus = $this->verifiedAlumnus()->user;
        $survey = $this->create();
        [$q1, $q2] = $survey->questions;

        $this->actingAs($alumnus)->post(route('surveys.submit', $survey), ['answers' => [$q1->id => 'Teleport', $q2->id => 11]])
            ->assertSessionHasErrors(["answers.{$q1->id}", "answers.{$q2->id}"]);
        $this->actingAs($alumnus)->post(route('surveys.submit', $survey), ['answers' => [$q1->id => 'Online']])->assertSessionHasErrors("answers.{$q2->id}");
    }

    public function test_only_the_audience_can_answer(): void
    {
        $survey = $this->create();
        $student = $this->user(RoleName::Student);

        $this->actingAs($student)->get(route('surveys.show', $survey))->assertNotFound();
        $this->actingAs($student)->post(route('surveys.submit', $survey), ['answers' => [1 => 'x']])->assertSessionHas('error');
    }

    public function test_event_surveys_are_for_checked_in_attendees_only(): void
    {
        $event = Event::factory()->create();
        $attended = $this->verifiedAlumnus()->user;
        $noShow = $this->verifiedAlumnus()->user;
        foreach ([[$attended, now()], [$noShow, null]] as [$u, $checkedIn]) {
            (new EventRegistration(['event_id' => $event->id, 'user_id' => $u->id]))->forceFill(['status' => 'confirmed', 'ticket_code' => str()->random(40), 'checked_in_at' => $checkedIn])->save();
        }

        $survey = $this->create(['event_id' => $event->id, 'audience' => null]);

        $this->assertTrue(app(SurveyService::class)->isEligible($attended, $survey));
        $this->assertFalse(app(SurveyService::class)->isEligible($noShow, $survey));
    }

    public function test_results_aggregate_and_nps_is_computed(): void
    {
        $survey = $this->create();
        [$q1, $q2] = $survey->questions;
        foreach ([[10, 'Online'], [9, 'Online'], [3, 'In person'], [7, 'Online']] as [$score, $fmt]) {
            $this->actingAs($this->verifiedAlumnus()->user)->post(route('surveys.submit', $survey), ['answers' => [$q1->id => $fmt, $q2->id => $score]]);
        }

        $this->actingAs($this->admin(RoleName::AlumniAdmin))->get(route('admin.surveys.results', $survey))->assertInertia(fn (Assert $p) => $p
            ->where('responses', 4)
            ->where('results.0.options.1.value', 3) // Online
            ->where('results.1.nps', 25)); // (2 promoters - 1 detractor) / 4
    }

    public function test_publishing_needs_staff_and_drafts_are_invisible(): void
    {
        $this->actingAs($this->user(RoleName::Student))->get(route('admin.surveys.index'))->assertForbidden();

        $admin = $this->admin(RoleName::AlumniAdmin);
        $this->actingAs($admin)->post(route('admin.surveys.store'), ['title' => 'Draft', 'audience' => ['roles' => ['alumni']], 'questions' => [['type' => 'rating', 'prompt' => 'Rate us', 'required' => true]]]);
        $this->actingAs($this->verifiedAlumnus()->user)->get(route('surveys.show', Survey::sole()))->assertNotFound();

        $this->actingAs($admin)->post(route('admin.surveys.store'), ['title' => 'Bad', 'questions' => [['type' => 'single', 'prompt' => 'Pick', 'options' => ['Only one']]]])->assertSessionHasErrors('questions.0.options');
    }
}
