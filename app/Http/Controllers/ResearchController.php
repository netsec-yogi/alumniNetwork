<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\ResearchInterest;
use App\Models\ResearchOpportunity;
use App\Notifications\Notice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Research collaboration board (SRS 42). Faculty and verified alumni post; members express interest. */
class ResearchController extends Controller
{
    private function canPost(Request $request): bool
    {
        $u = $request->user();

        return $u->hasRole(RoleName::Faculty->value) || (bool) $u->alumniProfile?->isVerified() || $u->can('content.manage');
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isCommunityMember(), 403);
        $f = $request->validate(['type' => ['nullable', Rule::in(array_keys(ResearchOpportunity::TYPES))], 'mine' => ['nullable', 'boolean']]);
        $user = $request->user();

        return Inertia::render('Research/Index', [
            'opportunities' => ResearchOpportunity::query()
                ->when(! empty($f['mine']), fn ($q) => $q->where('posted_by', $user->id), fn ($q) => $q->open())
                ->when($f['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
                ->with('poster:id,name')->withCount('interests')->latest()
                ->paginate(15)->withQueryString()
                ->through(fn (ResearchOpportunity $o) => [
                    'id' => $o->id, 'type' => ResearchOpportunity::TYPES[$o->type], 'title' => $o->title, 'organization' => $o->organization,
                    'areas' => $o->areas ?? [], 'closes_on' => $o->closes_on?->format('j M Y'), 'poster' => $o->poster->name,
                    'interests' => $o->posted_by === $user->id ? $o->interests_count : null, 'is_open' => $o->isOpen(),
                ]),
            'filters' => (object) $f,
            'types' => collect(ResearchOpportunity::TYPES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'canPost' => $this->canPost($request),
        ]);
    }

    public function show(Request $request, ResearchOpportunity $opportunity): Response
    {
        abort_unless($request->user()->isCommunityMember(), 403);
        $user = $request->user();
        $isPoster = $opportunity->posted_by === $user->id;
        abort_unless($opportunity->isOpen() || $isPoster, 404);
        $opportunity->load('poster.alumniProfile:id,user_id,verification_status');

        return Inertia::render('Research/Show', [
            'opportunity' => [
                'id' => $opportunity->id, 'type' => ResearchOpportunity::TYPES[$opportunity->type], 'title' => $opportunity->title,
                'description' => $opportunity->description, 'organization' => $opportunity->organization, 'areas' => $opportunity->areas ?? [],
                'closes_on' => $opportunity->closes_on?->format('j M Y'), 'is_open' => $opportunity->isOpen(),
                'poster' => ['name' => $opportunity->poster->name, 'profile_id' => $opportunity->poster->alumniProfile?->isVerified() ? $opportunity->poster->alumniProfile->id : null],
            ],
            'isPoster' => $isPoster,
            'myInterest' => $opportunity->interests()->where('user_id', $user->id)->value('note'),
            // Only the poster sees who is interested, with an email to reply.
            'interests' => $isPoster ? $opportunity->interests()->with('user:id,name,email')->latest()->get()
                ->map(fn (ResearchInterest $i) => ['name' => $i->user->name, 'email' => $i->user->email, 'note' => $i->note, 'at' => $i->created_at->diffForHumans()]) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canPost($request), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(ResearchOpportunity::TYPES))],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:30', 'max:5000'],
            'areas' => ['array', 'max:10'],
            'areas.*' => ['string', 'max:40'],
            'organization' => ['nullable', 'string', 'max:160'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $o = new ResearchOpportunity($data);
        $o->forceFill(['posted_by' => $request->user()->id, 'status' => 'open'])->save();

        return redirect()->route('research.show', $o)->with('success', 'Posted.');
    }

    public function close(Request $request, ResearchOpportunity $opportunity): RedirectResponse
    {
        abort_unless($opportunity->posted_by === $request->user()->id || $request->user()->can('content.manage'), 403);
        $opportunity->forceFill(['status' => 'closed'])->save();

        return back()->with('success', 'Closed.');
    }

    public function interest(Request $request, ResearchOpportunity $opportunity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember() && $opportunity->isOpen() && $opportunity->posted_by !== $user->id, 403);
        $note = $request->validate(['note' => ['required', 'string', 'min:20', 'max:2000']])['note'];

        $interest = ResearchInterest::firstOrNew(['research_opportunity_id' => $opportunity->id, 'user_id' => $user->id]);
        $isNew = ! $interest->exists;
        $interest->fill(['note' => $note])->save();

        if ($isNew) {
            $opportunity->poster->notify(new Notice("{$user->name} is interested in “{$opportunity->title}”", route('research.show', $opportunity, false)));
        }

        return back()->with('success', 'Sent. The poster will contact you by email.');
    }
}
