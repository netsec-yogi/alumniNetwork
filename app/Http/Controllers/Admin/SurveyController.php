<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Programme;
use App\Models\Survey;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SurveyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Survey authoring, publishing and results (module 31). */
class SurveyController extends Controller
{
    public function __construct(private readonly SurveyService $surveys, private readonly AuditLogger $audit) {}

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can(Permission::CommunicationsSend->value) || $request->user()->can(Permission::EventsUpdate->value), 403);
    }

    public function index(Request $request): Response
    {
        $this->guard($request);

        return Inertia::render('Admin/Surveys/Index', [
            'surveys' => Survey::withCount('responses')->with('event:id,title')->latest()->get()->map(fn (Survey $s) => [
                'slug' => $s->slug, 'title' => $s->title, 'status' => $s->status, 'responses' => $s->responses_count,
                'audience' => $s->event ? "Attendees of {$s->event->title}" : 'Audience segment', 'anonymous' => $s->is_anonymous,
                'closes_at' => $s->closes_at?->format('j M Y'),
            ]),
        ]);
    }

    public function form(Request $request, ?Survey $survey = null): Response
    {
        $this->guard($request);
        abort_if($survey && $survey->status !== 'draft', 409);

        return Inertia::render('Admin/Surveys/Form', [
            'survey' => $survey ? [
                ...$survey->only(['slug', 'title', 'description', 'event_id', 'is_anonymous']),
                'audience' => $survey->audience ?? ['roles' => ['alumni']],
                'closes_at' => $survey->closes_at?->format('Y-m-d\TH:i'),
                'questions' => $survey->questions->map->only(['type', 'prompt', 'options', 'required']),
            ] : null,
            'types' => collect(Survey::QUESTION_TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'events' => Event::published()->where('starts_at', '>', now()->subMonths(6))->orderByDesc('starts_at')->get(['id', 'title', 'starts_at'])
                ->map(fn ($e) => ['value' => $e->id, 'label' => $e->title.' ('.$e->starts_at->format('j M Y').')']),
            'programmes' => Programme::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
        ]);
    }

    public function save(Request $request, ?Survey $survey = null): RedirectResponse
    {
        $this->guard($request);
        abort_if($survey && $survey->status !== 'draft', 409);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_id' => ['nullable', 'exists:events,id'],
            'audience' => ['nullable', 'array'],
            'audience.roles' => ['nullable', 'array'],
            'audience.roles.*' => [Rule::in(['alumni', 'student', 'faculty'])],
            'audience.programmes' => ['nullable', 'array'],
            'audience.graduation_from' => ['nullable', 'integer'],
            'audience.graduation_to' => ['nullable', 'integer'],
            'is_anonymous' => ['boolean'],
            'closes_at' => ['nullable', 'date', 'after:now'],
            'questions' => ['required', 'array', 'min:1', 'max:30'],
            'questions.*.type' => ['required', Rule::in(array_keys(Survey::QUESTION_TYPES))],
            'questions.*.prompt' => ['required', 'string', 'max:500'],
            'questions.*.options' => ['nullable', 'array', 'max:12'],
            'questions.*.options.*' => ['string', 'max:120', 'distinct'],
            'questions.*.required' => ['boolean'],
        ]);
        foreach ($data['questions'] as $i => $q) {
            if (in_array($q['type'], ['single', 'multiple'], true) && count(array_filter($q['options'] ?? [])) < 2) {
                return back()->withErrors(["questions.{$i}.options" => 'Choice questions need at least two options.']);
            }
        }

        DB::transaction(function () use (&$survey, $data, $request) {
            $survey ??= (new Survey)->forceFill(['created_by' => $request->user()->id, 'status' => 'draft']);
            $survey->fill([
                'title' => $data['title'], 'description' => $data['description'] ?? null, 'event_id' => $data['event_id'] ?? null,
                'audience' => isset($data['event_id']) ? null : array_filter($data['audience'] ?? [], fn ($v) => $v !== null && $v !== []),
                'is_anonymous' => $data['is_anonymous'] ?? false, 'closes_at' => $data['closes_at'] ?? null,
            ])->save();

            $survey->questions()->delete();
            foreach ($data['questions'] as $i => $q) {
                $survey->questions()->create([
                    'position' => $i, 'type' => $q['type'], 'prompt' => $q['prompt'], 'required' => $q['required'] ?? true,
                    'options' => in_array($q['type'], ['single', 'multiple'], true) ? array_values(array_filter($q['options'])) : null,
                ]);
            }
        });

        return redirect()->route('admin.surveys.index')->with('success', 'Survey saved as a draft.');
    }

    public function publish(Request $request, Survey $survey): RedirectResponse
    {
        $this->guard($request);
        abort_unless($survey->status === 'draft', 409);

        try {
            $count = $this->surveys->publish($survey);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->audit->record('survey.published', 'surveys', $survey, null, ['notified' => $count]);

        return back()->with('success', "Published; {$count} people were invited.");
    }

    public function close(Request $request, Survey $survey): RedirectResponse
    {
        $this->guard($request);
        $survey->forceFill(['status' => 'closed'])->save();

        return back()->with('success', 'Closed.');
    }

    public function results(Request $request, Survey $survey): Response
    {
        $this->guard($request);

        return Inertia::render('Admin/Surveys/Results', [
            'survey' => ['slug' => $survey->slug, 'title' => $survey->title, 'status' => $survey->status, 'anonymous' => $survey->is_anonymous],
            'responses' => $survey->responses()->count(),
            'invited' => $this->surveys->eligibleQuery($survey)->count(),
            'results' => $this->surveys->results($survey->load('questions')),
        ]);
    }

    public function export(Request $request, Survey $survey): StreamedResponse
    {
        $this->guard($request);
        $this->audit->record('survey.exported', 'surveys', $survey);
        $questions = $survey->questions;
        $safe = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;

        return response()->streamDownload(function () use ($survey, $questions, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Response', ...($survey->is_anonymous ? [] : ['Respondent']), 'Submitted', ...$questions->pluck('prompt')]);
            $survey->responses()->with('answers')->orderBy('id')->chunkById(500, function ($responses) use ($out, $questions, $survey, $safe) {
                $names = $survey->is_anonymous ? collect() : User::whereIn('id', $responses->pluck('user_id'))->pluck('name', 'id');
                foreach ($responses as $r) {
                    $byQ = $r->answers->keyBy('survey_question_id');
                    fputcsv($out, array_map($safe, [
                        $r->id, ...($survey->is_anonymous ? [] : [$names[$r->user_id] ?? '']), $r->created_at->format('Y-m-d H:i'),
                        ...$questions->map(fn ($q) => is_array($v = $byQ[$q->id]->value ?? null) ? implode('; ', $v) : $v)->all(),
                    ]));
                }
            });
            fclose($out);
        }, "survey-{$survey->slug}.csv", ['Content-Type' => 'text/csv']);
    }
}
