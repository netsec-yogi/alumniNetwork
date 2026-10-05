<?php

namespace Tests\Feature\Events;

use App\Enums\RoleName;
use App\Models\EngagementActivity;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\EventReminder;
use App\Notifications\PromotedFromWaitlist;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    public function test_member_registers_and_gets_a_ticket(): void
    {
        Notification::fake();
        $event = Event::factory()->create(['capacity' => 10]);
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->post(route('events.register', $event), ['guests' => 1])->assertSessionHas('success');

        $r = EventRegistration::firstOrFail();
        $this->assertSame(EventRegistration::CONFIRMED, $r->status);
        $this->assertSame(40, strlen($r->ticket_code));
        $this->actingAs($user)->get(route('events.ticket', $event))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Events/Ticket')->where('guests', 1)->missing('ticket_code'));
    }

    public function test_capacity_counts_guests_and_overflow_goes_to_waitlist_in_order(): void
    {
        Notification::fake();
        $event = Event::factory()->create(['capacity' => 3, 'max_guests' => 2]);
        [$a, $b, $c] = [$this->verifiedAlumnus()->user, $this->verifiedAlumnus()->user, $this->verifiedAlumnus()->user];

        $this->actingAs($a)->post(route('events.register', $event), ['guests' => 1]); // 2 seats
        $this->actingAs($b)->post(route('events.register', $event), ['guests' => 1]); // needs 2, 1 left -> waitlist
        $this->actingAs($c)->post(route('events.register', $event)); // 1 would fit, but must not overtake b

        $status = fn ($u) => EventRegistration::where('user_id', $u->id)->value('status');
        $this->assertSame('confirmed', $status($a));
        $this->assertSame('waitlisted', $status($b));
        $this->assertSame('waitlisted', $status($c));

        // a cancels: 3 free -> b (2) promoted, then c (1) promoted.
        $this->actingAs($a)->delete(route('events.cancel', $event))->assertSessionHas('success');
        $this->assertSame('confirmed', $status($b));
        $this->assertSame('confirmed', $status($c));
        Notification::assertSentTo([$b, $c], PromotedFromWaitlist::class);
    }

    public function test_guest_limit_and_closed_registration_are_enforced(): void
    {
        $event = Event::factory()->create(['max_guests' => 0, 'registration_closes_at' => now()->addDay()]);
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->post(route('events.register', $event), ['guests' => 1])->assertSessionHas('error');

        $closed = Event::factory()->create(['registration_closes_at' => now()->subMinute()]);
        $this->actingAs($user)->post(route('events.register', $closed))->assertSessionHas('error');
        $this->assertSame(0, EventRegistration::count());
    }

    public function test_member_only_events_are_hidden_from_guests_and_unverified_users(): void
    {
        $members = Event::factory()->create();
        $public = Event::factory()->public()->create();
        $draft = Event::factory()->draft()->public()->create();

        $this->get(route('events.show', $public))->assertOk();
        $this->get(route('events.show', $members))->assertForbidden();
        $this->get(route('events.show', $draft))->assertForbidden();
        $this->get(route('events.index'))->assertInertia(fn (Assert $p) => $p->where('events.total', 1));

        $pending = $this->verifiedAlumnus(['verification_status' => 'pending'])->user;
        $this->actingAs($pending)->post(route('events.register', $members))->assertForbidden();
        $this->actingAs($pending)->post(route('events.register', $public))->assertSessionHas('success');
    }

    public function test_online_link_is_only_shown_to_confirmed_registrants(): void
    {
        $event = Event::factory()->create(['is_online' => true, 'online_url' => 'https://meet.example.com/abc', 'venue' => null]);
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->get(route('events.show', $event))->assertInertia(fn (Assert $p) => $p->where('event.online_url', null));
        $this->actingAs($user)->post(route('events.register', $event));
        $this->actingAs($user)->get(route('events.show', $event))->assertInertia(fn (Assert $p) => $p->where('event.online_url', 'https://meet.example.com/abc'));
    }

    /** SRS 37: duplicate check-in is prevented. */
    public function test_check_in_works_once_and_records_attendance(): void
    {
        $event = Event::factory()->create();
        $alumnus = $this->verifiedAlumnus()->user;
        $this->actingAs($alumnus)->post(route('events.register', $event));
        $code = EventRegistration::firstOrFail()->ticket_code;
        $staff = $this->admin(RoleName::EventManager);

        $this->actingAs($staff)->post(route('admin.events.check-in.store', $event), ['code' => $code])->assertSessionHas('success');
        $this->actingAs($staff)->post(route('admin.events.check-in.store', $event), ['code' => $code])->assertSessionHas('error');

        // The full URL from a phone scan works too, and wrong-event tickets are refused.
        $other = Event::factory()->create();
        $this->actingAs($staff)->post(route('admin.events.check-in.store', $other), ['code' => route('admin.events.check-in', ['event' => $other, 'code' => $code])])
            ->assertSessionHas('error', 'Ticket not recognised for this event.');

        $this->assertSame(1, EngagementActivity::where('activity_type', 'EVENT_ATTENDED')->count());
    }

    public function test_only_attendance_staff_can_check_in_or_export(): void
    {
        $event = Event::factory()->create();
        $alumnus = $this->verifiedAlumnus()->user;

        $this->actingAs($alumnus)->post(route('admin.events.check-in.store', $event), ['code' => 'x'])->assertForbidden();
        $this->actingAs($this->admin(RoleName::ChapterAdmin))->get(route('admin.events.export', $event))->assertForbidden();
        $this->actingAs($this->admin(RoleName::EventManager))->get(route('admin.events.export', $event))->assertOk();
    }

    public function test_event_manager_creates_publishes_and_cancels(): void
    {
        Notification::fake();
        $manager = $this->admin(RoleName::EventManager);

        $this->actingAs($manager)->post(route('admin.events.store'), [
            'title' => 'Hyderabad Chapter Meetup', 'type' => 'chapter', 'audience' => 'members',
            'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'ends_at' => now()->addWeek()->addHours(2)->format('Y-m-d\TH:i'),
            'is_online' => false, 'venue' => 'T-Hub', 'max_guests' => 0, 'status' => 'published',
        ])->assertRedirect();

        $event = Event::firstOrFail();
        $this->assertSame(Event::DRAFT, $event->status, 'status is never taken from input');

        $this->actingAs($manager)->post(route('admin.events.publish', $event))->assertSessionHas('success');
        $this->actingAs($this->verifiedAlumnus()->user)->post(route('events.register', $event));

        $this->actingAs($manager)->post(route('admin.events.cancel', $event), ['reason' => 'Venue unavailable'])->assertSessionHas('success');
        $this->assertSame(Event::CANCELLED, $event->fresh()->status);
        Notification::assertCount(2); // registration confirmation + cancellation
    }

    public function test_online_events_need_an_https_link(): void
    {
        $this->actingAs($this->admin(RoleName::EventManager))->post(route('admin.events.store'), [
            'title' => 'Webinar', 'type' => 'webinar', 'audience' => 'public', 'is_online' => true,
            'online_url' => 'javascript:alert(1)', 'max_guests' => 0,
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('online_url');
    }

    public function test_reminders_are_sent_once_for_events_within_a_day(): void
    {
        Notification::fake();
        $soon = Event::factory()->create(['starts_at' => now()->addHours(5), 'ends_at' => now()->addHours(7)]);
        $later = Event::factory()->create();
        $user = $this->verifiedAlumnus()->user;
        $this->actingAs($user)->post(route('events.register', $soon));
        $this->actingAs($user)->post(route('events.register', $later));

        $this->artisan('events:send-reminders')->assertSuccessful();
        $this->artisan('events:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($user, EventReminder::class, 1);
    }

    public function test_calendar_file_and_csv_export_are_safe(): void
    {
        $event = Event::factory()->public()->create(['title' => 'Meet, greet; repeat']);
        $this->get(route('events.calendar', $event))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('SUMMARY:Meet\, greet\; repeat', false);

        $attacker = $this->verifiedAlumnus()->user;
        $attacker->forceFill(['name' => '=HYPERLINK("http://evil")'])->save();
        $this->actingAs($attacker)->post(route('events.register', $event));

        $csv = $this->actingAs($this->admin(RoleName::EventManager))->get(route('admin.events.export', $event))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
