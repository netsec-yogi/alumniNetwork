<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EngagementActivity;
use App\Models\User;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerSignup;
use App\Notifications\Notice;
use App\Services\AuditLogger;
use App\Services\EngagementRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Volunteering (SRS 44): sign up -> do it -> log hours and outcome ->
 * organiser approves -> VOLUNTEERED engagement with the approved hours.
 */
class VolunteeringController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Content managers organise anywhere; group admins for their own groups. */
    private function canOrganise(User $user, ?VolunteerOpportunity $o = null): bool
    {
        if ($user->can(Permission::ContentManage->value)) {
            return true;
        }
        $groups = CommunityMember::where(['user_id' => $user->id, 'role' => 'admin', 'status' => 'active'])->pluck('community_id');

        return $o ? ($o->created_by === $user->id || $groups->contains($o->community_id)) : $groups->isNotEmpty();
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember(), 403);
        $category = $request->validate(['category' => ['nullable', Rule::in(array_keys(VolunteerOpportunity::CATEGORIES))]])['category'] ?? null;
        $mine = VolunteerSignup::where('user_id', $user->id)->pluck('status', 'volunteer_opportunity_id');

        return Inertia::render('Volunteering/Index', [
            'opportunities' => VolunteerOpportunity::open()
                ->when($category, fn ($q) => $q->where('category', $category))
                ->with('community:id,name')->withCount(['signups as taken' => fn ($q) => $q->whereNotIn('status', ['withdrawn', 'rejected'])])
                ->orderByRaw('starts_on IS NULL')->orderBy('starts_on')
                ->paginate(15)->withQueryString()
                ->through(fn (VolunteerOpportunity $o) => [
                    ...$this->card($o),
                    'my_status' => $mine[$o->id] ?? null,
                ]),
            'mySignups' => VolunteerSignup::where('user_id', $user->id)->whereNot('status', 'withdrawn')->with('opportunity')->latest()->get()
                ->map(fn (VolunteerSignup $s) => [
                    'id' => $s->id, 'title' => $s->opportunity->title, 'status' => $s->status, 'hours' => $s->hours,
                    'can_log' => $s->status === 'signed_up', 'date' => $s->opportunity->starts_on?->format('j M Y'),
                ]),
            'totalHours' => (float) VolunteerSignup::where('user_id', $user->id)->where('status', 'completed')->sum('hours'),
            'category' => $category,
            'categories' => collect(VolunteerOpportunity::CATEGORIES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'canOrganise' => $this->canOrganise($user),
            'groups' => $this->organisableGroups($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canOrganise($user), 403);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(VolunteerOpportunity::CATEGORIES))],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'location' => ['nullable', 'string', 'max:160'],
            'is_remote' => ['boolean'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'slots' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'hours_estimate' => ['nullable', 'numeric', 'min:0.5', 'max:500'],
            'community_id' => $user->can(Permission::ContentManage->value)
                ? ['nullable', 'exists:communities,id']
                : ['required', Rule::in(collect($this->organisableGroups($user))->pluck('value'))],
        ]);

        $o = new VolunteerOpportunity($data);
        $o->forceFill(['created_by' => $user->id, 'status' => 'open'])->save();
        $this->audit->record('volunteering.created', 'volunteering', $o);

        return back()->with('success', 'Opportunity posted.');
    }

    public function signUp(Request $request, VolunteerOpportunity $opportunity): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->alumniProfile?->isVerified() || $user->hasRole('faculty'), 403);
        $motivation = $request->validate(['motivation' => ['nullable', 'string', 'max:1000']])['motivation'] ?? null;

        return DB::transaction(function () use ($user, $opportunity, $motivation) {
            $o = VolunteerOpportunity::whereKey($opportunity->id)->lockForUpdate()->firstOrFail();
            if ($o->status !== 'open' || ($o->ends_on && $o->ends_on->lt(today()))) {
                return back()->with('error', 'This opportunity is closed.');
            }
            if ($o->slots && $o->activeSignups() >= $o->slots) {
                return back()->with('error', 'All slots are taken.');
            }

            $signup = VolunteerSignup::firstOrNew(['volunteer_opportunity_id' => $o->id, 'user_id' => $user->id]);
            if ($signup->exists && $signup->status !== 'withdrawn') {
                return back()->with('error', 'You’ve already signed up.');
            }
            $signup->forceFill(['volunteer_opportunity_id' => $o->id, 'user_id' => $user->id, 'status' => 'signed_up', 'motivation' => $motivation])->save();
            $o->creator->notify(new Notice("{$user->name} volunteered for “{$o->title}”", route('volunteering.manage', $o, false)));

            return back()->with('success', 'Thanks for volunteering! The organiser will be in touch.');
        });
    }

    public function withdraw(Request $request, VolunteerSignup $signup): RedirectResponse
    {
        abort_unless($signup->user_id === $request->user()->id && $signup->status === 'signed_up', 403);
        $signup->forceFill(['status' => 'withdrawn'])->save();

        return back()->with('success', 'Withdrawn.');
    }

    public function logHours(Request $request, VolunteerSignup $signup): RedirectResponse
    {
        abort_unless($signup->user_id === $request->user()->id && $signup->status === 'signed_up', 403);
        $data = $request->validate(['hours' => ['required', 'numeric', 'min:0.5', 'max:500'], 'outcome' => ['required', 'string', 'min:10', 'max:2000']]);

        $signup->forceFill(['hours' => round($data['hours'] * 2) / 2, 'outcome' => $data['outcome'], 'status' => 'hours_submitted'])->save();

        return back()->with('success', 'Hours submitted for approval.');
    }

    public function manage(Request $request, VolunteerOpportunity $opportunity): Response
    {
        abort_unless($this->canOrganise($request->user(), $opportunity), 403);

        return Inertia::render('Volunteering/Manage', [
            'opportunity' => $this->card($opportunity->loadCount(['signups as taken' => fn ($q) => $q->whereNotIn('status', ['withdrawn', 'rejected'])])),
            'signups' => $opportunity->signups()->with('user:id,name,email')->orderByRaw("FIELD(status, 'hours_submitted', 'signed_up', 'completed', 'rejected', 'withdrawn')")->get()
                ->map(fn (VolunteerSignup $s) => [
                    'id' => $s->id, 'name' => $s->user->name, 'email' => $s->user->email, 'status' => $s->status,
                    'motivation' => $s->motivation, 'hours' => $s->hours, 'outcome' => $s->outcome,
                ]),
        ]);
    }

    public function approve(Request $request, VolunteerSignup $signup, EngagementRecorder $engagement): RedirectResponse
    {
        abort_unless($this->canOrganise($request->user(), $signup->opportunity), 403);
        abort_unless($signup->status === 'hours_submitted', 409);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'hours' => ['nullable', 'numeric', 'min:0.5', 'max:500']]);

        if ($data['decision'] === 'approve') {
            $signup->forceFill(['status' => 'completed', 'hours' => $data['hours'] ?? $signup->hours, 'approved_by' => $request->user()->id, 'approved_at' => now()])->save();
            $engagement->record($signup->user, 'VOLUNTEERED', EngagementActivity::MODE_VOLUNTEER, $signup, null, ['hours' => $signup->hours]);
        } else {
            $signup->forceFill(['status' => 'rejected'])->save();
        }

        $this->audit->record("volunteering.hours_{$data['decision']}d", 'volunteering', $signup, null, ['hours' => $signup->hours]);
        $signup->user->notify(new Notice(
            $data['decision'] === 'approve' ? "Thank you! {$signup->hours} volunteer hours recorded for “{$signup->opportunity->title}”" : "Your volunteer hours for “{$signup->opportunity->title}” weren’t approved",
            route('volunteering.index', [], false),
        ));

        return back()->with('success', $data['decision'] === 'approve' ? 'Hours approved.' : 'Hours rejected.');
    }

    public function close(Request $request, VolunteerOpportunity $opportunity): RedirectResponse
    {
        abort_unless($this->canOrganise($request->user(), $opportunity), 403);
        $opportunity->forceFill(['status' => 'closed'])->save();

        return back()->with('success', 'Closed to new sign-ups.');
    }

    private function card(VolunteerOpportunity $o): array
    {
        return [
            'id' => $o->id, 'title' => $o->title, 'category' => VolunteerOpportunity::CATEGORIES[$o->category] ?? $o->category,
            'description' => $o->description, 'location' => $o->is_remote ? 'Remote' : $o->location, 'status' => $o->status,
            'dates' => collect([$o->starts_on?->format('j M Y'), $o->ends_on?->format('j M Y')])->filter()->unique()->implode(' – ') ?: null,
            'slots' => $o->slots, 'taken' => $o->taken ?? null, 'hours_estimate' => $o->hours_estimate, 'group' => $o->community?->name,
            'can_manage' => $this->canOrganise(request()->user(), $o),
        ];
    }

    private function organisableGroups(User $user): array
    {
        return Community::query()
            ->when(! $user->can(Permission::ContentManage->value), fn ($q) => $q->whereHas('memberships', fn ($m) => $m->where(['user_id' => $user->id, 'role' => 'admin', 'status' => 'active'])))
            ->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all();
    }
}
