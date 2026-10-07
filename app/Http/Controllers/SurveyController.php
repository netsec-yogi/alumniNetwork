<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Services\SurveyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SurveyController extends Controller
{
    public function __construct(private readonly SurveyService $surveys) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $open = Survey::where('status', 'published')->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()))->latest()->get()
            ->filter(fn (Survey $s) => $this->surveys->isEligible($user, $s));

        return Inertia::render('Surveys/Index', [
            'surveys' => $open->map(fn (Survey $s) => [
                'slug' => $s->slug, 'title' => $s->title, 'description' => $s->description, 'anonymous' => $s->is_anonymous,
                'closes_at' => $s->closes_at?->format('j M Y'), 'done' => $this->surveys->hasResponded($user, $s),
            ])->values(),
        ]);
    }

    public function show(Request $request, Survey $survey): Response
    {
        $user = $request->user();
        abort_unless($survey->status !== 'draft' && $this->surveys->isEligible($user, $survey), 404);

        return Inertia::render('Surveys/Show', [
            'survey' => [
                'slug' => $survey->slug, 'title' => $survey->title, 'description' => $survey->description,
                'anonymous' => $survey->is_anonymous, 'open' => $survey->isOpen(), 'closes_at' => $survey->closes_at?->format('j M Y, g:i A'),
                'questions' => $survey->questions->map->only(['id', 'type', 'prompt', 'options', 'required']),
            ],
            'done' => $this->surveys->hasResponded($user, $survey),
        ]);
    }

    public function submit(Request $request, Survey $survey): RedirectResponse
    {
        $answers = $request->validate(['answers' => ['required', 'array']])['answers'];

        try {
            $this->surveys->submit($request->user(), $survey, $answers);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Thank you for your answers!');
    }
}
