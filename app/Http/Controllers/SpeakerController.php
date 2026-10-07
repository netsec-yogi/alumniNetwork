<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\EngagementActivity;
use App\Models\Programme;
use App\Models\SpeakerInvitation;
use App\Models\SpeakerProfile;
use App\Models\User;
use App\Notifications\Notice;
use App\Services\ConnectionService;
use App\Services\EngagementRecorder;
use App\Services\ProfileVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Alumni speaker network (SRS 43). */
class SpeakerController extends Controller
{
    public function __construct(private readonly ConnectionService $connections) {}

    private function canSpeak(User $u): bool
    {
        return (bool) $u->alumniProfile?->isVerified() || $u->hasRole(RoleName::Faculty->value);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->isCommunityMember(), 403);
        $f = $request->validate([
            'topic' => ['nullable', 'string', 'max:60'],
            'format' => ['nullable', Rule::in(array_keys(SpeakerProfile::FORMATS))],
            'industry' => ['nullable', 'string', 'max:100'],
            'programme' => ['nullable', 'integer'],
            'in_person' => ['nullable', 'boolean'],
        ]);
        $blocked = $this->connections->blockedIds($user->id);

        $speakers = SpeakerProfile::where('is_available', true)
            ->whereNotIn('user_id', $blocked)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->when($f['topic'] ?? null, fn ($q, $t) => $q->whereRaw('LOWER(JSON_EXTRACT(topics, "$")) LIKE ?', ['%'.Str::lower(addcslashes($t, '%_\\')).'%']))
            ->when($f['format'] ?? null, fn ($q, $v) => $q->whereJsonContains('formats', $v))
            ->when($f['industry'] ?? null, fn ($q, $v) => $q->whereHas('user.alumniProfile', fn ($p) => $p->where('industry', 'like', '%'.addcslashes($v, '%_\\').'%')))
            ->when($f['programme'] ?? null, fn ($q, $v) => $q->whereHas('user.alumniProfile', fn ($p) => $p->where('programme_id', $v)))
            ->when(! empty($f['in_person']), fn ($q) => $q->where('in_person', true))
            ->with(['user:id,name', 'user.alumniProfile:id,user_id,programme_id,graduation_year,company,designation,industry,photo_file_id,verification_status,visibility', 'user.alumniProfile.programme:id,code', 'user.alumniProfile.photo'])
            ->latest()->paginate(18)->withQueryString()
            ->through(fn (SpeakerProfile $s) => $this->card($s, $user));

        return Inertia::render('Speakers/Index', [
            'speakers' => $speakers,
            'filters' => (object) $f,
            'formats' => $this->options(SpeakerProfile::FORMATS),
            'programmes' => Programme::orderBy('name')->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name]),
            'mine' => $user->speakerProfile ? true : false,
            'canSpeak' => $this->canSpeak($user),
            'invitations' => [
                'received' => SpeakerInvitation::where('speaker_id', $user->id)->with('inviter:id,name')->latest()->limit(20)->get()->map(fn ($i) => $this->invitation($i, $i->inviter)),
                'sent' => SpeakerInvitation::where('inviter_id', $user->id)->with('speaker:id,name')->latest()->limit(20)->get()->map(fn ($i) => $this->invitation($i, $i->speaker)),
            ],
        ]);
    }

    public function editProfile(Request $request): Response
    {
        abort_unless($this->canSpeak($request->user()), 403);

        return Inertia::render('Speakers/Profile', ['profile' => $request->user()->speakerProfile, 'formats' => $this->options(SpeakerProfile::FORMATS)]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        abort_unless($this->canSpeak($request->user()), 403);
        $data = $request->validate([
            'is_available' => ['boolean'],
            'topics' => ['required', 'array', 'min:1', 'max:15'],
            'topics.*' => ['string', 'max:40'],
            'formats' => ['required', 'array', 'min:1'],
            'formats.*' => [Rule::in(array_keys(SpeakerProfile::FORMATS))],
            'bio' => ['nullable', 'string', 'max:2000'],
            'languages' => ['nullable', 'string', 'max:120'],
            'remote' => ['boolean'],
            'in_person' => ['boolean'],
        ]);
        $request->user()->speakerProfile()->updateOrCreate([], $data);

        return redirect()->route('speakers.index')->with('success', 'Speaker profile saved.');
    }

    public function invite(Request $request, User $speaker): RedirectResponse
    {
        $user = $request->user();
        $profile = $speaker->speakerProfile;
        abort_unless($user->isCommunityMember() && $profile?->is_available && $speaker->id !== $user->id && ! $this->connections->isBlockedEitherWay($user->id, $speaker->id), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'details' => ['required', 'string', 'min:30', 'max:3000'],
            'format' => ['required', Rule::in($profile->formats)],
            'proposed_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $invitation = new SpeakerInvitation($data);
        $invitation->forceFill(['speaker_id' => $speaker->id, 'inviter_id' => $user->id, 'status' => 'pending'])->save();
        $speaker->notify(new Notice("{$user->name} invited you to speak: {$data['title']}", route('speakers.index', [], false), null, true));

        return back()->with('success', 'Invitation sent.');
    }

    public function respond(Request $request, SpeakerInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->speaker_id === $request->user()->id && $invitation->status === 'pending', 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])], 'note' => ['nullable', 'string', 'max:500']]);

        $invitation->forceFill(['status' => $data['decision'] === 'accept' ? 'accepted' : 'declined', 'response_note' => $data['note'] ?? null, 'responded_at' => now()])->save();
        $invitation->inviter->notify(new Notice("{$request->user()->name} {$invitation->status} your speaking invitation", route('speakers.index', [], false), $invitation->response_note));

        return back()->with('success', 'Response sent.');
    }

    /** The organiser confirms the talk happened; the speaker earns engagement credit. */
    public function delivered(Request $request, SpeakerInvitation $invitation, EngagementRecorder $engagement): RedirectResponse
    {
        abort_unless($invitation->inviter_id === $request->user()->id && $invitation->status === 'accepted', 403);
        $invitation->forceFill(['status' => 'delivered'])->save();
        $engagement->record($invitation->speaker, 'GUEST_LECTURE', EngagementActivity::MODE_VOLUNTEER, $invitation);

        return back()->with('success', 'Marked as delivered. Thank you note sent.');
    }

    private function card(SpeakerProfile $s, User $viewer): array
    {
        $p = $s->user->alumniProfile;
        $visibility = app(ProfileVisibility::class);

        return [
            'user_id' => $s->user_id,
            'name' => $p?->displayName() ?? $s->user->name,
            'subtitle' => $p ? "{$p->programme->code} · {$p->graduation_year}" : 'Faculty',
            'role' => $p && $visibility->canSee($p, 'company', $viewer) ? collect([$p->designation, $p->company])->filter()->implode(', ') : null,
            'photo_url' => $p?->photo?->url(true),
            'profile_id' => $p?->isVerified() ? $p->id : null,
            'topics' => $s->topics, 'formats' => collect($s->formats)->map(fn ($f) => SpeakerProfile::FORMATS[$f] ?? $f)->values(),
            'format_keys' => $s->formats, 'bio' => $s->bio, 'languages' => $s->languages, 'remote' => $s->remote, 'in_person' => $s->in_person,
            'is_me' => $s->user_id === $viewer->id,
        ];
    }

    private function invitation(SpeakerInvitation $i, User $other): array
    {
        return [
            'id' => $i->id, 'title' => $i->title, 'details' => $i->details, 'format' => SpeakerProfile::FORMATS[$i->format] ?? $i->format,
            'date' => $i->proposed_date?->format('j M Y'), 'status' => $i->status, 'note' => $i->response_note, 'other' => $other->name,
        ];
    }

    private function options(array $map): array
    {
        return array_map(fn ($v, $l) => ['value' => $v, 'label' => $l], array_keys($map), $map);
    }
}
