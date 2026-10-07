<?php

namespace Tests\Feature\Ai;

use App\Enums\RoleName;
use App\Models\MentorProfile;
use App\Models\Programme;
use App\Services\Ai\AiReply;
use App\Services\Ai\LanguageModel;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeLanguageModel;
use Tests\TestCase;

class AiFeaturesTest extends TestCase
{
    private function fake(array $responses): FakeLanguageModel
    {
        config(['ai.enabled' => true]);
        $fake = new FakeLanguageModel($responses);
        $this->app->instance(LanguageModel::class, $fake);

        return $fake;
    }

    public function test_everything_is_off_unless_enabled(): void
    {
        config(['ai.enabled' => false]);
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->get(route('assistant'))->assertNotFound();
        $this->actingAs($user)->post(route('assistant.ask'), ['message' => 'hi'])->assertNotFound();
        $this->actingAs($user)->post(route('directory.ai-search'), ['query' => 'people at Google'])->assertNotFound();
        $this->actingAs($user)->get(route('directory'))->assertInertia(fn (Assert $p) => $p->where('features.ai', false));
    }

    public function test_smart_search_becomes_directory_filters_and_sends_no_member_data(): void
    {
        $programme = Programme::firstOrFail();
        $viewer = $this->verifiedAlumnus(['company' => 'Secretco'])->user;
        $fake = $this->fake([['name' => null, 'programme' => $programme->name, 'graduation_year' => 2015, 'company' => 'Google', 'location' => 'Bengaluru', 'interest' => 'not-a-real-interest', 'explanation' => 'Alumni at Google in Bengaluru']]);

        $this->actingAs($viewer)->post(route('directory.ai-search'), ['query' => '2015 grads at Google in Bangalore'])
            ->assertRedirect(route('directory', ['programme' => $programme->id, 'year' => 2015, 'company' => 'Google', 'location' => 'Bengaluru']))
            ->assertSessionHas('success', 'Showing: Alumni at Google in Bengaluru');

        $sent = json_encode($fake->calls);
        $this->assertStringContainsString('2015 grads at Google in Bangalore', $sent);
        $this->assertStringNotContainsString('Secretco', $sent);
        $this->assertStringNotContainsString($viewer->email, $sent);
    }

    public function test_smart_search_failure_falls_back_to_filters(): void
    {
        $this->fake([null]);

        $this->actingAs($this->verifiedAlumnus()->user)->from(route('directory'))
            ->post(route('directory.ai-search'), ['query' => 'anything at all'])
            ->assertRedirect(route('directory'))->assertSessionHas('warning');
    }

    public function test_mentor_rerank_orders_by_ai_and_keeps_names_out_of_the_prompt(): void
    {
        $mentors = collect([1, 2, 3])->map(function () {
            $a = $this->verifiedAlumnus();
            MentorProfile::factory()->create(['user_id' => $a->user_id, 'categories' => ['career']]);

            return $a->user;
        });
        $fake = $this->fake([['ranking' => [['candidate' => 2, 'reason' => 'Best fit.'], ['candidate' => 0, 'reason' => 'Good.']]]]);

        $response = $this->actingAs($this->user(RoleName::Student))
            ->get(route('mentoring.find', ['category' => 'career', 'goals' => 'Preparing for product roles']));

        $response->assertInertia(fn (Assert $p) => $p->where('aiEnabled', true)->has('results', 3)
            ->where('results.0.ai_reason', 'Best fit.')->where('results.1.ai_reason', 'Good.')->missing('results.2.ai_reason'));

        $prompt = $fake->calls[0]['prompt'];
        $this->assertStringContainsString('Preparing for product roles', $prompt);
        foreach ($mentors as $m) {
            $this->assertStringNotContainsString($m->name, $prompt);
            $this->assertStringNotContainsString($m->email, $prompt);
        }
    }

    public function test_assistant_tools_run_as_the_user_and_respect_privacy(): void
    {
        $this->verifiedAlumnus(['company' => 'HiddenCorp', 'city' => 'Pune', 'visibility' => ['company' => 'private']]);
        $this->verifiedAlumnus(['company' => 'OpenCorp', 'city' => 'Pune', 'visibility' => ['company' => 'members']]);
        // Pinned: a random city could be Pune and change the count.
        $viewer = $this->verifiedAlumnus(['city' => 'Shillong', 'country' => 'India'])->user;

        $fake = $this->fake([
            new AiReply('tool_use', [['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'search_alumni', 'input' => ['location' => 'Pune']]], [], [['id' => 'tu_1', 'name' => 'search_alumni', 'input' => ['location' => 'Pune']]]),
            new AiReply('end_turn', [], ['Two alumni live in Pune.']),
        ]);

        $this->actingAs($viewer)->post(route('assistant.ask'), ['message' => 'Who lives in Pune?'])->assertRedirect();

        $toolResult = $fake->calls[1]['messages'][2]['content'][0];
        $this->assertSame('tu_1', $toolResult['toolUseID']);
        $this->assertStringContainsString('OpenCorp', $toolResult['content']);
        $this->assertStringNotContainsString('HiddenCorp', $toolResult['content']);
        $this->assertSame(2, json_decode($toolResult['content'], true)['total']);

        $this->actingAs($viewer)->get(route('assistant'))->assertInertia(fn (Assert $p) => $p->component('Assistant/Index')
            ->where('history', [['role' => 'user', 'content' => 'Who lives in Pune?'], ['role' => 'assistant', 'content' => 'Two alumni live in Pune.']]));
    }

    public function test_every_assistant_tool_runs(): void
    {
        $calls = [
            ['id' => 't1', 'name' => 'upcoming_events', 'input' => ['keyword' => 'meet']],
            ['id' => 't2', 'name' => 'search_jobs', 'input' => ['type' => 'job']],
            ['id' => 't3', 'name' => 'find_mentors', 'input' => ['area' => 'career', 'topics' => ['ml']]],
            ['id' => 't4', 'name' => 'my_activity', 'input' => []],
            ['id' => 't5', 'name' => 'drop_tables', 'input' => []],
        ];
        $mentor = $this->verifiedAlumnus();
        MentorProfile::factory()->create(['user_id' => $mentor->user_id, 'categories' => ['career']]);
        $fake = $this->fake([new AiReply('tool_use', [], [], $calls), new AiReply('end_turn', [], ['Done.'])]);

        $this->actingAs($this->verifiedAlumnus()->user)->post(route('assistant.ask'), ['message' => 'Everything please'])->assertRedirect();

        $results = collect($fake->calls[1]['messages'][2]['content'])->mapWithKeys(fn ($r) => [$r['toolUseID'] => json_decode($r['content'], true)]);
        $this->assertArrayHasKey('events', $results['t1']);
        $this->assertArrayHasKey('jobs', $results['t2']);
        $this->assertCount(1, $results['t3']['mentors']);
        $this->assertArrayHasKey('upcoming_registrations', $results['t4']);
        $this->assertSame('Unknown tool.', $results['t5']['error']);
    }

    public function test_assistant_history_is_capped_and_resettable(): void
    {
        config(['ai.assistant.history_turns' => 2]);
        $this->fake(array_map(fn ($i) => new AiReply('end_turn', [], ["answer {$i}"]), range(1, 3)));
        $user = $this->verifiedAlumnus()->user;

        foreach (range(1, 3) as $i) {
            $this->actingAs($user)->post(route('assistant.ask'), ['message' => "question {$i}"]);
        }

        $history = session('assistant.history');
        $this->assertCount(4, $history);
        $this->assertSame('question 2', $history[0]['content']);

        $this->actingAs($user)->delete(route('assistant.reset'));
        $this->assertNull(session('assistant.history'));
    }

    public function test_assistant_refusal_gets_a_polite_reply(): void
    {
        $this->fake([new AiReply('refusal', [])]);
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->post(route('assistant.ask'), ['message' => 'something off-topic']);

        $this->assertSame('I can’t help with that request.', session('assistant.history')[1]['content']);
    }

    public function test_unverified_members_cannot_use_the_assistant(): void
    {
        $this->fake([]);
        $pending = $this->verifiedAlumnus(['verification_status' => 'pending'])->user;

        $this->actingAs($pending)->get(route('assistant'))->assertForbidden();
    }
}
