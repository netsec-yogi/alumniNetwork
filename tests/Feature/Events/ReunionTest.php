<?php

namespace Tests\Feature\Events;

use App\Enums\RoleName;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\Consent;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\EventRegistration;
use App\Services\EventRegistrationService;
use App\Services\Payments\FakeGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReunionTest extends TestCase
{
    private function paidEvent(array $o = []): Event
    {
        return Event::factory()->create(array_merge(['fee_paise' => 150000, 'capacity' => 10, 'max_guests' => 1], $o));
    }

    private function returnFor(EventRegistration $r, string $status = 'paid'): string
    {
        $pid = $status === 'paid' ? 'pay_1' : '';

        return route('events.payment-return', ['reference' => $r->reference, 'status' => $status, 'payment_id' => $pid, 'signature' => FakeGateway::sign($r->gateway_order_id, $status, $pid)]);
    }

    public function test_paid_registration_holds_seat_then_confirms_on_payment(): void
    {
        Notification::fake();
        $event = $this->paidEvent();
        $user = $this->verifiedAlumnus()->user;

        $this->actingAs($user)->post(route('events.register', $event), ['guests' => 1])->assertRedirectContains('/pay/test-checkout/event/');
        $r = EventRegistration::sole();
        $this->assertSame([EventRegistration::PAYMENT_PENDING, 300000], [$r->status, $r->amount_paise], 'fee covers the guest');
        $this->assertSame(2, $event->confirmedSeats(), 'the hold counts against capacity');

        // Tickets aren't issued before payment.
        $this->actingAs($user)->get(route('events.ticket', $event))->assertNotFound();

        $this->actingAs($user)->get($this->returnFor($r))->assertRedirect(route('events.show', $event));
        $this->assertSame([EventRegistration::CONFIRMED, 'paid'], [$r->fresh()->status, $r->fresh()->payment_status]);
        $this->actingAs($user)->get(route('events.ticket', $event))->assertOk();
    }

    public function test_forged_return_does_not_confirm_and_others_cannot_use_it(): void
    {
        $event = $this->paidEvent();
        $user = $this->verifiedAlumnus()->user;
        $this->actingAs($user)->post(route('events.register', $event));
        $r = EventRegistration::sole();

        $this->actingAs($user)->get(route('events.payment-return', ['reference' => $r->reference, 'status' => 'paid', 'payment_id' => 'x', 'signature' => 'forged']));
        $this->assertSame(EventRegistration::PAYMENT_PENDING, $r->fresh()->status);

        $this->actingAs($this->verifiedAlumnus()->user)->get($this->returnFor($r))->assertNotFound();
    }

    public function test_expired_holds_are_released_and_waitlist_promoted_with_payment_window(): void
    {
        Notification::fake();
        $event = $this->paidEvent(['capacity' => 1, 'max_guests' => 0]);
        [$a, $b] = [$this->verifiedAlumnus()->user, $this->verifiedAlumnus()->user];
        $service = app(EventRegistrationService::class);
        $service->register($a, $event);
        $service->register($b, $event);
        $this->assertSame(EventRegistration::WAITLISTED, EventRegistration::where('user_id', $b->id)->value('status'));

        $this->travel(31)->minutes();
        $this->artisan('events:release-holds')->assertSuccessful();

        $ra = EventRegistration::where('user_id', $a->id)->first();
        $rb = EventRegistration::where('user_id', $b->id)->first();
        $this->assertSame(EventRegistration::CANCELLED, $ra->status);
        $this->assertSame(EventRegistration::PAYMENT_PENDING, $rb->status);
        $this->assertTrue($rb->hold_expires_at->gt(now()->addHours(47)), 'promoted attendee gets 48 hours to pay');
    }

    public function test_paid_registrations_cannot_be_cancelled_online(): void
    {
        $event = $this->paidEvent();
        $user = $this->verifiedAlumnus()->user;
        $this->actingAs($user)->post(route('events.register', $event));
        $r = EventRegistration::sole();
        app(EventRegistrationService::class)->markPaid($r, 'pay_1');

        $this->actingAs($user)->delete(route('events.cancel', $event))->assertSessionHas('error');
    }

    public function test_batch_invitation_targets_reunion_years_and_respects_consent(): void
    {
        Mail::fake();
        Notification::fake();
        $event = Event::factory()->create(['type' => 'reunion', 'batch_years' => [2012]]);
        $in = $this->verifiedAlumnus(['graduation_year' => 2012])->user;
        $optedIn = $this->verifiedAlumnus(['graduation_year' => 2012])->user;
        $optedIn->consents()->create(['consent_type' => Consent::COMMUNICATIONS, 'version' => 't', 'granted' => true]);
        $this->verifiedAlumnus(['graduation_year' => 2015]);

        $this->actingAs($this->admin(RoleName::EventManager))->post(route('admin.events.invite-batch', $event))->assertSessionHas('success');

        $this->assertSame(2, Campaign::sole()->recipients_count);
        Mail::assertQueued(CampaignMail::class, 1);
        Mail::assertQueued(CampaignMail::class, fn ($m) => $m->recipient->is($optedIn));
    }

    public function test_attendees_upload_photos_after_the_event(): void
    {
        Storage::fake('local');
        $event = Event::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHours(3)]);
        $attendee = $this->verifiedAlumnus()->user;
        (new EventRegistration(['event_id' => $event->id, 'user_id' => $attendee->id]))->forceFill(['status' => 'confirmed', 'ticket_code' => str()->random(40)])->save();
        $png = tempnam(sys_get_temp_dir(), 'p');
        imagepng(imagecreatetruecolor(30, 30), $png);

        $this->actingAs($this->verifiedAlumnus()->user)->post(route('events.photos.store', $event), ['photo' => new UploadedFile($png, 'x.png', null, null, true)])->assertForbidden();
        $this->actingAs($attendee)->post(route('events.photos.store', $event), ['photo' => new UploadedFile($png, 'x.png', null, null, true), 'caption' => 'Batch photo!'])->assertSessionHas('success');

        $photo = EventPhoto::sole();
        $this->actingAs($this->verifiedAlumnus()->user)->delete(route('events.photos.destroy', $photo))->assertForbidden();
        $this->actingAs($this->admin(RoleName::EventManager))->delete(route('events.photos.destroy', $photo))->assertSessionHas('success');
    }
}
