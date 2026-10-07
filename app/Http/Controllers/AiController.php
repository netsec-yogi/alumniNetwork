<?php

namespace App\Http\Controllers;

use App\Models\AlumniProfile;
use App\Services\Ai\AiSearchInterpreter;
use App\Services\Ai\AlumniAssistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI features (SRS 96). All of them are off unless AI_ENABLED and an API key
 * are configured; every answer is built from data the user could already see.
 */
class AiController extends Controller
{
    private const HISTORY_KEY = 'assistant.history';

    public function search(Request $request, AiSearchInterpreter $interpreter): RedirectResponse
    {
        abort_unless(config('ai.enabled'), 404);
        $this->authorize('viewAny', AlumniProfile::class);
        $data = $request->validate(['query' => ['required', 'string', 'min:3', 'max:300']]);

        $result = $interpreter->interpret($data['query']);
        if ($result === null) {
            return back()->with('warning', 'Smart search couldn’t read that — try the filters instead.');
        }
        if ($result['filters'] === []) {
            return back()->with('warning', 'That didn’t match anything the directory can filter by. Try naming a company, city, batch or programme.');
        }

        return redirect()->route('directory', $result['filters'])->with('success', 'Showing: '.$result['explanation']);
    }

    public function assistant(Request $request): Response
    {
        abort_unless(config('ai.enabled'), 404);
        abort_unless($request->user()->isCommunityMember(), 403);

        return Inertia::render('Assistant/Index', [
            'history' => $request->session()->get(self::HISTORY_KEY, []),
        ]);
    }

    public function ask(Request $request, AlumniAssistant $assistant): RedirectResponse
    {
        abort_unless(config('ai.enabled'), 404);
        abort_unless($request->user()->isCommunityMember(), 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);

        // Only plain-text turns persist between requests; tool traffic stays inside one answer.
        $history = $request->session()->get(self::HISTORY_KEY, []);
        $answer = $assistant->ask($request->user(), $history, $data['message']);

        $history[] = ['role' => 'user', 'content' => $data['message']];
        $history[] = ['role' => 'assistant', 'content' => $answer['reply']];
        $request->session()->put(self::HISTORY_KEY, array_slice($history, -2 * config('ai.assistant.history_turns')));

        return back();
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::HISTORY_KEY);

        return back();
    }
}
