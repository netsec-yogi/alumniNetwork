<?php

namespace Tests\Feature\Giving;

use App\Enums\RoleName;
use App\Mail\DonationReceiptMail;
use App\Models\Donation;
use App\Models\EngagementActivity;
use App\Services\DonationService;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DonationTest extends TestCase
{
    private function payload(array $o = []): array
    {
        return array_merge([
            'category' => 'scholarship', 'amount' => 2500, 'donor_name' => 'Asha Rao', 'donor_email' => 'asha@example.com',
            'indian_resident' => true,
        ], $o);
    }

    private function payViaFakeGateway(Donation $d, string $status = 'paid'): TestResponse
    {
        $pid = $status === 'paid' ? 'pay_test_1' : '';

        return $this->get(route('donations.return', ['donation' => $d->reference, 'status' => $status, 'payment_id' => $pid, 'signature' => FakeGateway::sign($d->gateway_order_id, $status, $pid)]));
    }

    public function test_full_flow_receipt_and_engagement(): void
    {
        Storage::fake('local');
        Mail::fake();
        $alumnus = $this->verifiedAlumnus()->user;

        $this->actingAs($alumnus)->post(route('giving.store'), $this->payload(['wants_80g' => true, 'pan' => 'abcde1234f', 'address' => 'Gwalior']))->assertRedirectContains('/pay/test-checkout/donation/');
        $d = Donation::sole();
        $this->assertSame(['pending', 250000], [$d->status, $d->amount_paise]);
        $this->assertSame('ABCDE1234F', $d->pan);
        $this->assertNotSame('ABCDE1234F', \DB::table('donations')->value('pan'), 'PAN is encrypted at rest');

        $this->payViaFakeGateway($d)->assertOk();
        $d->refresh();
        $this->assertSame('paid', $d->status);
        $this->assertSame('IIITM/ALU/'.DonationService::financialYear(now()).'/000001', $d->receipt_number);
        $this->assertNotNull($d->receipt_file_id);
        Mail::assertSent(DonationReceiptMail::class, fn ($m) => $m->hasTo('asha@example.com'));
        $this->assertSame(1, EngagementActivity::where(['activity_type' => 'DONATION', 'engagement_mode' => 'philanthropic'])->count());

        // Donor downloads their own receipt; others can't.
        $this->actingAs($alumnus)->get(route('files.show', $d->receipt))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->verifiedAlumnus()->user)->get(route('files.show', $d->receipt))->assertForbidden();
    }

    public function test_tampered_return_does_not_mark_paid(): void
    {
        $this->post(route('giving.store'), $this->payload());
        $d = Donation::sole();

        $this->get(route('donations.return', ['donation' => $d->reference, 'status' => 'paid', 'payment_id' => 'x', 'signature' => 'forged']))->assertOk();
        $this->assertSame('pending', $d->fresh()->status);
    }

    public function test_paying_twice_yields_one_receipt_and_numbers_are_sequential(): void
    {
        Mail::fake();
        $this->post(route('giving.store'), $this->payload());
        $this->post(route('giving.store'), $this->payload(['donor_email' => 'b@example.com']));
        [$a, $b] = Donation::orderBy('id')->get();

        $this->payViaFakeGateway($a);
        $this->payViaFakeGateway($a); // repeated redirect
        app(DonationService::class)->markPaid($a, 'pay_test_1'); // late webhook
        $this->payViaFakeGateway($b);

        $this->assertStringEndsWith('/000001', $a->fresh()->receipt_number);
        $this->assertStringEndsWith('/000002', $b->fresh()->receipt_number);
    }

    public function test_pan_and_residency_rules(): void
    {
        $this->post(route('giving.store'), $this->payload(['amount' => 50000]))->assertSessionHasErrors('pan');
        $this->post(route('giving.store'), $this->payload(['wants_80g' => true]))->assertSessionHasErrors(['pan', 'address']);
        $this->post(route('giving.store'), $this->payload(['pan' => 'BADPAN']))->assertSessionHasErrors('pan');
        $this->post(route('giving.store'), $this->payload(['indian_resident' => false]))->assertSessionHasErrors('indian_resident');
        $this->post(route('giving.store'), $this->payload(['amount' => 10]))->assertSessionHasErrors('amount');
        $this->assertSame(0, Donation::count());
    }

    public function test_financial_year_boundaries(): void
    {
        $this->assertSame('2026-27', DonationService::financialYear(Carbon::parse('2026-04-01')));
        $this->assertSame('2025-26', DonationService::financialYear(Carbon::parse('2026-03-31')));
    }

    public function test_razorpay_webhook_signature_replay_and_amount_checks(): void
    {
        Mail::fake();
        config(['payments.gateway' => 'razorpay', 'payments.razorpay' => ['key_id' => 'rzp_test', 'key_secret' => 'secret', 'webhook_secret' => 'whsec']]);
        $this->app->bind(PaymentGateway::class, RazorpayGateway::class);
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response(['id' => 'plink_123', 'short_url' => 'https://rzp.io/i/abc'])]);

        $this->withHeader('X-Inertia', 'true')->post(route('giving.store'), $this->payload())->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://rzp.io/i/abc');
        $d = Donation::sole();
        $this->assertSame('plink_123', $d->gateway_order_id);

        $event = fn (int $amount) => json_encode(['event' => 'payment_link.paid', 'payload' => [
            'payment_link' => ['entity' => ['id' => 'plink_123']],
            'payment' => ['entity' => ['id' => 'pay_999', 'amount' => $amount]],
        ]]);
        $send = fn (string $body, string $sig, string $id) => $this->call('POST', route('webhooks.payments', 'razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $sig, 'HTTP_X_RAZORPAY_EVENT_ID' => $id,
        ], $body);

        $send($event(250000), 'forged', 'evt_1')->assertStatus(400);
        $this->assertSame('pending', $d->fresh()->status);

        $body = $event(250000);
        $send($body, hash_hmac('sha256', $body, 'whsec'), 'evt_1')->assertOk();
        $this->assertSame(['paid', 'pay_999'], [$d->fresh()->status, $d->fresh()->gateway_payment_id]);

        // Replay of the same event is accepted but ignored.
        $send($body, hash_hmac('sha256', $body, 'whsec'), 'evt_1')->assertOk();
        $this->assertSame(1, \DB::table('payment_events')->count());
    }

    public function test_webhook_amount_mismatch_is_rejected(): void
    {
        $this->post(route('giving.store'), $this->payload());
        $d = Donation::sole();

        $this->expectException(\InvalidArgumentException::class);
        app(DonationService::class)->markPaid($d, 'pay_x', 100);
    }

    public function test_refunds_need_permission_and_remove_engagement(): void
    {
        Mail::fake();
        $alumnus = $this->verifiedAlumnus()->user;
        $this->actingAs($alumnus)->post(route('giving.store'), $this->payload());
        $d = Donation::sole();
        $this->payViaFakeGateway($d);

        $this->actingAs($this->admin(RoleName::EventManager))->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.donations.refund', $d), ['reason' => 'Duplicate'])->assertForbidden();
        $this->actingAs($this->admin(RoleName::FundraisingManager))->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.donations.refund', $d), ['reason' => 'Donor request: duplicate'])->assertSessionHas('success');

        $this->assertSame('refunded', $d->fresh()->status);
        $this->assertSame(0, EngagementActivity::where('activity_type', 'DONATION')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'donation.refunded', 'entity_id' => $d->id]);
    }

    public function test_test_gateway_is_unavailable_in_production(): void
    {
        $donation = (new Donation)->forceFill(['reference' => 'DON-X', 'donor_name' => 'x', 'donor_email' => 'x@example.com', 'category' => 'general', 'amount_paise' => 10000, 'gateway' => 'fake']);
        $donation->save();
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        (new FakeGateway)->checkout($donation);
    }
}
