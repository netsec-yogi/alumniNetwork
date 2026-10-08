<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventRequest;
use App\Jobs\DeliverCampaign;
use App\Models\Campaign;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\AuditLogger;
use App\Services\EventRegistrationService;
use App\Services\MediaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Event management, attendees and check-in (SRS 35-37). */
class EventController extends Controller
{
    public function __construct(
        private readonly EventRegistrationService $registrations,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Event::class);
        $status = $request->validate(['status' => ['nullable', Rule::in(['upcoming', 'past', 'draft', 'cancelled'])]])['status'] ?? 'upcoming';

        $events = Event::query()
            ->when($status === 'upcoming', fn ($q) => $q->published()->upcoming()->orderBy('starts_at'))
            ->when($status === 'past', fn ($q) => $q->published()->where('ends_at', '<', now())->orderByDesc('starts_at'))
            ->when(in_array($status, ['draft', 'cancelled'], true), fn ($q) => $q->where('status', $status)->latest())
            ->withCount([
                'registrations as confirmed' => fn ($q) => $q->where('status', EventRegistration::CONFIRMED),
                'registrations as waitlisted' => fn ($q) => $q->where('status', EventRegistration::WAITLISTED),
                'registrations as checked_in' => fn ($q) => $q->whereNotNull('checked_in_at'),
            ])
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Event $e) => [
                'id' => $e->id,
                'slug' => $e->slug,
                'title' => $e->title,
                'type_label' => $e->type->label(),
                'starts_at' => $e->starts_at->format('D j M Y, g:i A'),
                'status' => $e->status,
                'capacity' => $e->capacity,
                'confirmed' => $e->confirmed,
                'waitlisted' => $e->waitlisted,
                'checked_in' => $e->checked_in,
            ]);

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
            'status' => $status,
            'canCreate' => $request->user()->can('create', Event::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Event::class);

        return Inertia::render('Admin/Events/Form', ['event' => null, 'typeOptions' => EventType::options(), 'groups' => $this->hostGroups()]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = new Event($request->eventData());
        $event->forceFill(['status' => Event::DRAFT, 'created_by' => $request->user()->id])->save();

        $this->audit->record('event.created', 'events', $event, null, ['title' => $event->title]);

        return redirect()->route('admin.events.show', $event)->with('success', 'Draft saved. Publish it when you’re ready.');
    }

    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);
        $dt = fn ($d) => $d?->format('Y-m-d\TH:i');

        return Inertia::render('Admin/Events/Form', [
            'event' => [
                ...$event->only(['id', 'slug', 'title', 'summary', 'description', 'venue', 'is_online', 'online_url', 'capacity', 'max_guests', 'audience', 'status', 'community_id', 'is_featured']),
                'fee' => $event->fee_paise / 100,
                'batch_years' => $event->batch_years ?? [],
                'type' => $event->type->value,
                'starts_at' => $dt($event->starts_at),
                'ends_at' => $dt($event->ends_at),
                'registration_opens_at' => $dt($event->registration_opens_at),
                'registration_closes_at' => $dt($event->registration_closes_at),
            ],
            'typeOptions' => EventType::options(),
            'groups' => $this->hostGroups(),
        ]);
    }

    /** Groups this user may host events for. */
    private function hostGroups(): array
    {
        $user = request()->user();

        return Community::query()
            ->when(! $user->can(Permission::EventsManageAttendance->value), fn ($q) => $q->whereHas('memberships', fn ($m) => $m->where(['user_id' => $user->id, 'role' => 'admin', 'status' => 'active'])))
            ->orderBy('kind')->orderBy('name')->get(['id', 'name', 'kind'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => ($c->kind === 'chapter' ? 'Chapter: ' : '').$c->name])
            ->all();
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $original = $event->getAttributes();

        DB::transaction(function () use ($request, $event) {
            $event->fill($request->eventData())->save();
            // Raising capacity may let waitlisted people in.
            if ($event->isPublished()) {
                Event::whereKey($event->id)->lockForUpdate()->first();
                $this->registrations->promoteWaitlist($event);
            }
        });

        $this->audit->recordChanges('event.updated', 'events', $event, $original);

        return redirect()->route('admin.events.show', $event)->with('success', 'Event updated.');
    }

    public function publish(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($event->status === Event::DRAFT, 409);

        $event->forceFill(['status' => Event::PUBLISHED])->save();
        $this->audit->record('event.published', 'events', $event);

        return back()->with('success', 'Published. Members can now register.');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($event->isPublished(), 409);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $this->registrations->cancelEvent($request->user(), $event, $data['reason']);

        return back()->with('success', 'Event cancelled; registrants are being notified.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        $event->delete();
        $this->audit->record('event.deleted', 'events', $event);

        return redirect()->route('admin.events.index', ['status' => 'draft'])->with('success', 'Draft deleted.');
    }

    public function show(Request $request, Event $event): Response
    {
        $this->authorize('viewAny', Event::class);
        $filter = $request->validate(['status' => ['nullable', Rule::in(['confirmed', 'waitlisted', 'cancelled', 'checked_in'])]])['status'] ?? null;

        $attendees = $event->registrations()
            ->with('user:id,name,email')
            ->when($filter === 'checked_in', fn ($q) => $q->whereNotNull('checked_in_at'))
            ->when($filter && $filter !== 'checked_in', fn ($q) => $q->where('status', $filter))
            ->orderBy('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (EventRegistration $r) => [
                'id' => $r->id,
                'name' => $r->user->name,
                'email' => $r->user->email,
                'status' => $r->status,
                'guests' => $r->guests,
                'registered_at' => $r->created_at->format('j M, g:i A'),
                'checked_in_at' => $r->checked_in_at?->format('j M, g:i A'),
            ]);

        $counts = $event->registrations()
            ->selectRaw('status, count(*) as n, sum(1 + guests) as seats')
            ->groupBy('status')->get()->keyBy('status');

        return Inertia::render('Admin/Events/Show', [
            'images' => $event->officialPhotos()->with('file')->get()->map(fn ($p) => ['id' => $p->id, 'thumb' => $p->file->url(true), 'full' => $p->file->url(), 'is_featured' => $p->is_featured]),
            'canManageImages' => $request->user()->can('update', $event),
            'imageLimitKb' => app(MediaSettings::class)->limit('event_image_kb'),
            'event' => [
                'id' => $event->id,
                'slug' => $event->slug,
                'title' => $event->title,
                'type_label' => $event->type->label(),
                'starts_at' => $event->starts_at->format('D j M Y, g:i A'),
                'venue' => $event->is_online ? 'Online' : $event->venue,
                'status' => $event->status,
                'capacity' => $event->capacity,
                'audience' => $event->audience,
                'cancellation_reason' => $event->cancellation_reason,
                'batch_years' => $event->batch_years ?? [],
                'fee' => $event->fee_paise / 100,
            ],
            'stats' => [
                'confirmed' => (int) ($counts['confirmed']->n ?? 0),
                'seats' => (int) ($counts['confirmed']->seats ?? 0),
                'waitlisted' => (int) ($counts['waitlisted']->n ?? 0),
                'cancelled' => (int) ($counts['cancelled']->n ?? 0),
                'checked_in' => $event->registrations()->whereNotNull('checked_in_at')->count(),
            ],
            'attendees' => $attendees,
            'filter' => $filter,
            'can' => [
                'update' => $request->user()->can('update', $event),
                'delete' => $request->user()->can('delete', $event),
                'attendance' => $request->user()->can('manageAttendance', $event),
            ],
        ]);
    }

    /**
     * Reunion invitations (SRS 38): email + in-app to the target batches,
     * through the communications engine so consent rules apply.
     */
    public function inviteBatch(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($event->isPublished() && ! empty($event->batch_years), 409);

        $campaign = new Campaign([
            'name' => "Invitation: {$event->title}",
            'subject' => "You’re invited: {$event->title}",
            'body' => "Your batch is getting together!\n\n**{$event->title}** — {$event->starts_at->format('l, j F Y, g:i A')}".($event->venue ? " at {$event->venue}" : '')
                ."\n\n".($event->summary ?? '')."\n\n[See details and register](".route('events.show', $event).')',
            'channels' => ['email', 'in_app'],
            'audience' => ['roles' => ['alumni'], 'graduation_years' => $event->batch_years],
        ]);
        $campaign->forceFill(['created_by' => $request->user()->id, 'status' => 'draft'])->save();
        DeliverCampaign::dispatch($campaign);
        $this->audit->record('event.batch_invited', 'events', $event, null, ['batches' => $event->batch_years, 'campaign_id' => $campaign->id]);

        return back()->with('success', 'Invitations are on their way to batches '.implode(', ', $event->batch_years).'.');
    }

    /** Attendee list as CSV; audited because it contains personal data (SRS 95). */
    public function export(Request $request, Event $event): StreamedResponse
    {
        $this->authorize('manageAttendance', $event);
        $this->audit->record('event.attendees_exported', 'events', $event);

        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Status', 'Guests', 'Registered at', 'Checked in at']);
            $event->registrations()->with('user:id,name,email')->orderBy('created_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $r->user->name, $r->user->email, $r->status, $r->guests,
                        $r->created_at->format('Y-m-d H:i'), $r->checked_in_at?->format('Y-m-d H:i'),
                    ]));
                }
            });
            fclose($out);
        }, "attendees-{$event->slug}.csv", ['Content-Type' => 'text/csv']);
    }

    /** Stop spreadsheet formula injection from member-supplied text. */
    public function csvSafe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    public function checkInPage(Request $request, Event $event): Response
    {
        $this->authorize('manageAttendance', $event);

        return Inertia::render('Admin/Events/CheckIn', [
            'event' => ['id' => $event->id, 'slug' => $event->slug, 'title' => $event->title, 'starts_at' => $event->starts_at->format('D j M, g:i A')],
            'code' => $request->query('code'),
            'checkedIn' => $event->registrations()->whereNotNull('checked_in_at')->count(),
            'expected' => $event->registrations()->where('status', EventRegistration::CONFIRMED)->count(),
            'recent' => $event->registrations()->with('user:id,name')->whereNotNull('checked_in_at')
                ->latest('checked_in_at')->limit(8)->get()
                ->map(fn (EventRegistration $r) => ['name' => $r->user->name, 'guests' => $r->guests, 'at' => $r->checked_in_at->format('g:i A')]),
        ]);
    }

    public function checkIn(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageAttendance', $event);
        $code = $request->validate(['code' => ['required', 'string', 'max:500']])['code'];

        // Accept either the raw code or the full URL a phone scanner pastes.
        if (str_contains($code, 'code=')) {
            parse_str((string) parse_url($code, PHP_URL_QUERY), $query);
            $code = (string) ($query['code'] ?? '');
        }

        try {
            $r = $this->registrations->checkIn($request->user(), $event, $code);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.events.check-in', $event)->with('error', $e->getMessage());
        }

        $guests = $r->guests ? " + {$r->guests} guest".($r->guests > 1 ? 's' : '') : '';

        return redirect()->route('admin.events.check-in', $event)->with('success', "✓ {$r->user->name}{$guests} checked in.");
    }
}
