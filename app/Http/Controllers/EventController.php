<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\EventRegistration;
use App\Models\StoredFile;
use App\Services\EventRegistrationService;
use App\Services\Payments\InvalidSignature;
use App\Services\Payments\PaymentGateway;
use App\Services\Uploads\FileUploadService;
use App\Services\Uploads\UploadRejected;
use App\Support\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/** Events for members and the public (SRS 35-36). */
class EventController extends Controller
{
    public function __construct(private readonly EventRegistrationService $registrations) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(EventType::class)],
            'when' => ['nullable', Rule::in(['upcoming', 'past', 'mine'])],
        ]);
        $user = $request->user();
        $when = $filters['when'] ?? 'upcoming';

        $events = Event::query()
            ->published()
            ->when(! $user?->isCommunityMember(), fn ($q) => $q->where('audience', Event::AUDIENCE_PUBLIC))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($when === 'upcoming', fn ($q) => $q->upcoming()->orderBy('starts_at'))
            ->when($when === 'past', fn ($q) => $q->where('ends_at', '<', now())->orderByDesc('starts_at'))
            ->when($when === 'mine' && $user, fn ($q) => $q->whereHas('registrations', fn ($r) => $r
                ->where('user_id', $user->id)->where('status', '!=', EventRegistration::CANCELLED))->orderByDesc('starts_at'))
            ->withCount(['registrations as attending' => fn ($q) => $q->where('status', EventRegistration::CONFIRMED)])
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Event $e) => $this->card($e));

        return Inertia::render('Events/Index', [
            'events' => $events,
            'filters' => ['type' => $filters['type'] ?? null, 'when' => $when],
            'typeOptions' => EventType::options(),
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);
        $user = $request->user();

        $registration = $user ? $event->registrations()->where('user_id', $user->id)->first() : null;
        $active = $registration && $registration->status !== EventRegistration::CANCELLED ? $registration : null;
        $seatsLeft = $event->capacity === null ? null : max(0, $event->capacity - $event->confirmedSeats());

        return Inertia::render('Events/Show', [
            'event' => [
                ...$this->card($event),
                'description' => $event->description,
                'max_guests' => $event->max_guests,
                'registration_open' => $event->registrationOpen(),
                'registration_closes_at' => $event->registration_closes_at?->format('D j M Y, g:i A'),
                'seats_left' => $seatsLeft,
                'cancellation_reason' => $event->cancellation_reason,
                // The joining link is for confirmed registrants only.
                'online_url' => $active?->status === EventRegistration::CONFIRMED ? $event->online_url : null,
                'fee' => $event->fee_paise ? $event->fee_paise / 100 : 0,
                'batch_years' => $event->batch_years ?? [],
            ],
            'photos' => $event->starts_at->isPast() ? $event->photos()->with('file', 'uploader:id,name')->limit(60)->get()->map(fn ($p) => [
                'id' => $p->id, 'thumb' => $p->file->url(true), 'url' => $p->file->url(), 'caption' => $p->caption, 'by' => $p->uploader?->name,
                'can_delete' => $user && ($p->uploaded_by === $user->id || $user->can('manageAttendance', $event)),
            ]) : [],
            'canUploadPhotos' => $user && $event->starts_at->isPast() && ($active?->status === EventRegistration::CONFIRMED || $user->can('manageAttendance', $event)),
            'registration' => $active ? [
                'status' => $active->status,
                'guests' => $active->guests,
                'checked_in' => $active->checked_in_at !== null,
                'amount' => $active->amount_paise / 100,
                'payment_status' => $active->payment_status,
                'hold_expires_at' => $active->hold_expires_at?->format('j M, g:i A'),
                'waitlist_position' => $active->status === EventRegistration::WAITLISTED
                    ? $event->registrations()->where('status', EventRegistration::WAITLISTED)->where('created_at', '<=', $active->created_at)->count()
                    : null,
            ] : null,
            'canRegister' => $user?->can('register', $event) ?? false,
            'canManage' => $user?->can('update', $event) ?? false,
        ]);
    }

    public function register(Request $request, Event $event): HttpResponse|RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('register', $event);
        $data = $request->validate(['guests' => ['nullable', 'integer', 'min:0', 'max:20']]);

        try {
            $registration = $this->registrations->register($request->user(), $event, (int) ($data['guests'] ?? 0));
            if ($registration->status === EventRegistration::PAYMENT_PENDING) {
                return Inertia::location($this->registrations->checkout($registration));
            }
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $registration->status === EventRegistration::CONFIRMED
            ? 'You’re registered. Your ticket is ready.'
            : 'The event is full — you’re on the waitlist.');
    }

    /** Pay for a held seat (e.g. after promotion from the waitlist). */
    public function pay(Request $request, Event $event): \Symfony\Component\HttpFoundation\Response
    {
        $registration = $event->registrations()->where('user_id', $request->user()->id)->firstOrFail();

        try {
            return Inertia::location($this->registrations->checkout($registration));
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function paymentReturn(Request $request, string $reference, PaymentGateway $gateway): RedirectResponse
    {
        $registration = EventRegistration::where('reference', $reference)->with('event')->firstOrFail();
        abort_unless($registration->user_id === $request->user()->id, 404);

        try {
            $result = $gateway->verifyReturn($request, $registration);
            if ($result['status'] === 'paid') {
                $this->registrations->markPaid($registration, $result['payment_id']);

                return redirect()->route('events.show', $registration->event)->with('success', 'Payment received — you’re registered. Your ticket is ready.');
            }
        } catch (InvalidSignature) {
            Log::channel('security')->warning('Event payment return with invalid signature.', ['reference' => $reference]);
        }

        return redirect()->route('events.show', $registration->event)->with('error', 'The payment didn’t go through. Your seat is held until '.$registration->hold_expires_at?->format('g:i A').'.');
    }

    public function uploadPhoto(Request $request, Event $event, FileUploadService $uploads): RedirectResponse
    {
        $user = $request->user();
        $attended = $event->registrations()->where('user_id', $user->id)->where('status', EventRegistration::CONFIRMED)->exists();
        abort_unless(($attended || $user->can('manageAttendance', $event)) && $event->starts_at->isPast(), 403);
        $data = $request->validate(['photo' => ['required', 'file', 'max:'.config('security.uploads.max_image_kb')], 'caption' => ['nullable', 'string', 'max:200']]);

        try {
            $file = $uploads->storeImage($request->file('photo'), $user, 'event_photo', StoredFile::MEMBERS, 1600, 400);
        } catch (UploadRejected $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        }
        $photo = $event->photos()->make(['caption' => $data['caption'] ?? null]);
        $photo->forceFill(['file_id' => $file->id, 'uploaded_by' => $user->id])->save();

        return back()->with('success', 'Photo added to the album.');
    }

    public function deletePhoto(Request $request, EventPhoto $photo): RedirectResponse
    {
        abort_unless($photo->uploaded_by === $request->user()->id || $request->user()->can('manageAttendance', $photo->event), 403);
        $photo->file?->forceDelete();
        $photo->delete();

        return back()->with('success', 'Photo removed.');
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        $registration = $event->registrations()->where('user_id', $request->user()->id)->firstOrFail();

        try {
            $this->registrations->cancel($request->user(), $registration);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Registration cancelled.');
    }

    public function ticket(Request $request, Event $event): Response
    {
        $registration = $event->registrations()
            ->where('user_id', $request->user()->id)
            ->where('status', EventRegistration::CONFIRMED)
            ->firstOrFail();

        // The QR encodes the staff check-in URL: door staff scan it with any
        // phone camera; the page itself requires the check-in permission.
        $checkInUrl = route('admin.events.check-in', ['event' => $event, 'code' => $registration->ticket_code]);

        return Inertia::render('Events/Ticket', [
            'event' => $this->card($event) + ['online_url' => $event->online_url],
            'holder' => $request->user()->name,
            'guests' => $registration->guests,
            'reference' => Str::upper(substr($registration->ticket_code, 0, 8)),
            'checkedIn' => $registration->checked_in_at?->format('j M, g:i A'),
            'qrSvg' => QrCode::svg($checkInUrl),
        ]);
    }

    /** iCalendar file so members can add the event to any calendar app. */
    public function calendar(Request $request, Event $event): HttpResponse
    {
        $this->authorize('view', $event);

        $fmt = fn ($dt) => $dt->clone()->utc()->format('Ymd\THis\Z');
        $esc = fn (?string $s) => str_replace(['\\', ';', ',', "\r", "\n"], ['\\\\', '\;', '\,', '', '\n'], (string) $s);

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//ABV-IIITM//Alumni Connect//EN', 'BEGIN:VEVENT',
            "UID:event-{$event->id}@".parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($event->starts_at),
            'DTEND:'.$fmt($event->ends_at),
            'SUMMARY:'.$esc($event->title),
            'LOCATION:'.$esc($event->is_online ? 'Online' : $event->venue),
            'URL:'.route('events.show', $event),
            'END:VEVENT', 'END:VCALENDAR', '',
        ]);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$event->slug.'.ics"',
        ]);
    }

    /** @return array<string, mixed> */
    private function card(Event $e): array
    {
        return [
            'id' => $e->id,
            'slug' => $e->slug,
            'title' => $e->title,
            'type' => $e->type->value,
            'type_label' => $e->type->label(),
            'summary' => $e->summary,
            'starts_at' => $e->starts_at->format('D j M Y, g:i A'),
            'ends_at' => $e->ends_at->format('D j M Y, g:i A'),
            'date_badge' => ['day' => $e->starts_at->format('j'), 'month' => $e->starts_at->format('M')],
            'venue' => $e->venue,
            'is_online' => $e->is_online,
            'status' => $e->status,
            'audience' => $e->audience,
            'has_ended' => $e->hasEnded(),
            'capacity' => $e->capacity,
            'attending' => $e->attending ?? null,
        ];
    }
}
