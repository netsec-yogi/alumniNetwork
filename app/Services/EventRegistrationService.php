<?php

namespace App\Services;

use App\Models\EngagementActivity;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Notifications\EventCancelled;
use App\Notifications\EventRegistrationConfirmed;
use App\Notifications\PromotedFromWaitlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Event registration, waitlist and check-in (SRS 36-37).
 *
 * Capacity is enforced under a row lock on the event, so two people can
 * never take the last seat. The waitlist is strictly first-come: when seats
 * free up, the earliest waitlisted party is promoted if it fits, and nobody
 * behind it jumps the queue.
 */
class EventRegistrationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EngagementRecorder $engagement,
    ) {}

    public function register(User $user, Event $event, int $guests = 0): EventRegistration
    {
        $registration = DB::transaction(function () use ($user, $event, $guests) {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (! $event->registrationOpen()) {
                throw new InvalidArgumentException('Registration for this event is closed.');
            }
            if ($guests < 0 || $guests > $event->max_guests) {
                throw new InvalidArgumentException($event->max_guests ? "You can bring up to {$event->max_guests} guests." : 'This event does not allow guests.');
            }

            $registration = EventRegistration::firstOrNew(['event_id' => $event->id, 'user_id' => $user->id]);
            if ($registration->exists && $registration->status !== EventRegistration::CANCELLED) {
                throw new InvalidArgumentException('You are already registered.');
            }

            $seatsFree = $event->capacity === null ? PHP_INT_MAX : $event->capacity - $event->confirmedSeats();
            $waitlistExists = $event->registrations()->where('status', EventRegistration::WAITLISTED)->exists();
            // Don't let a newcomer overtake people already waiting.
            $confirmed = (1 + $guests) <= $seatsFree && ! $waitlistExists;

            $registration->forceFill([
                'status' => $confirmed ? EventRegistration::CONFIRMED : EventRegistration::WAITLISTED,
                'guests' => $guests,
                'ticket_code' => $registration->ticket_code ?? Str::random(40),
                'cancelled_at' => null,
                'checked_in_at' => null,
                'reminder_sent_at' => null,
            ]);
            // A re-registration joins the back of any queue.
            $registration->created_at = now();
            $registration->save();

            return $registration;
        });

        $user->notify(new EventRegistrationConfirmed($event, $registration->status));

        return $registration;
    }

    public function cancel(User $user, EventRegistration $registration): void
    {
        if ($registration->user_id !== $user->id) {
            throw new InvalidArgumentException('Not your registration.');
        }
        if ($registration->status === EventRegistration::CANCELLED) {
            return;
        }
        if ($registration->checked_in_at !== null) {
            throw new InvalidArgumentException('You have already checked in.');
        }

        DB::transaction(function () use ($registration) {
            Event::whereKey($registration->event_id)->lockForUpdate()->first();
            $registration->forceFill(['status' => EventRegistration::CANCELLED, 'cancelled_at' => now()])->save();
            $this->promoteWaitlist($registration->event);
        });
    }

    /** Promote waitlisted parties, in order, while they fit. Call inside a locked transaction. */
    public function promoteWaitlist(Event $event): void
    {
        if ($event->capacity === null && ! $event->registrations()->where('status', EventRegistration::WAITLISTED)->exists()) {
            return;
        }

        $free = $event->capacity === null ? PHP_INT_MAX : $event->capacity - $event->confirmedSeats();

        $waiting = $event->registrations()
            ->where('status', EventRegistration::WAITLISTED)
            ->orderBy('created_at')->orderBy('id')
            ->with('user')
            ->get();

        foreach ($waiting as $registration) {
            if ($registration->seats() > $free) {
                break;
            }
            $registration->forceFill(['status' => EventRegistration::CONFIRMED])->save();
            $free -= $registration->seats();
            DB::afterCommit(fn () => $registration->user->notify(new PromotedFromWaitlist($event)));
        }
    }

    /**
     * Check a ticket in. Returns the registration on success; throws with a
     * message the door staff can read out otherwise.
     */
    public function checkIn(User $staff, Event $event, string $code): EventRegistration
    {
        $registration = EventRegistration::where('ticket_code', trim($code))->with('user')->first();

        if ($registration === null || $registration->event_id !== $event->id) {
            throw new InvalidArgumentException('Ticket not recognised for this event.');
        }
        if ($registration->status !== EventRegistration::CONFIRMED) {
            throw new InvalidArgumentException("This registration is {$registration->status}, not confirmed.");
        }

        // Atomic: two scanners racing on the same ticket cannot both succeed.
        $updated = EventRegistration::whereKey($registration->id)
            ->whereNull('checked_in_at')
            ->update(['checked_in_at' => now(), 'checked_in_by' => $staff->id]);

        if ($updated === 0) {
            $at = $registration->fresh()->checked_in_at;
            throw new InvalidArgumentException("Already checked in at {$at->format('H:i')}.");
        }

        $this->engagement->record($registration->user, 'EVENT_ATTENDED', EngagementActivity::MODE_EXPERIENTIAL, $event, $event->starts_at);

        return $registration->fresh(['user']);
    }

    public function cancelEvent(User $staff, Event $event, string $reason): void
    {
        $event->forceFill(['status' => Event::CANCELLED, 'cancellation_reason' => $reason])->save();

        $event->registrations()
            ->whereIn('status', [EventRegistration::CONFIRMED, EventRegistration::WAITLISTED])
            ->with('user')
            ->chunkById(200, function ($registrations) use ($event) {
                foreach ($registrations as $r) {
                    $r->user->notify(new EventCancelled($event));
                }
            });

        $this->audit->record('event.cancelled', 'events', $event, null, ['reason' => $reason], $staff);
    }
}
