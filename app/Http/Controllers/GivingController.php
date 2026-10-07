<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\EventRegistration;
use App\Models\FundraisingCampaign;
use App\Services\DonationService;
use App\Services\EventRegistrationService;
use App\Services\Payments\InvalidSignature;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Giving (SRS 45-47): public donate page, hosted checkout, return, webhook. */
class GivingController extends Controller
{
    public function __construct(private readonly DonationService $donations) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $paid = Donation::where('status', Donation::PAID);

        return Inertia::render('Giving/Index', [
            'categories' => collect(config('payments.categories'))->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'limits' => ['min' => config('payments.min_amount'), 'max' => config('payments.max_amount'), 'pan_from' => config('payments.pan_required_from')],
            'prefill' => $user ? ['donor_name' => $user->name, 'donor_email' => $user->email, 'donor_phone' => $user->phone] : null,
            'stats' => ['donors' => (clone $paid)->distinct()->count('donor_email'), 'raised' => (int) floor((clone $paid)->sum('amount_paise') / 100)],
            // Donor wall: first name only, never amounts, and never anonymous gifts.
            'recent' => (clone $paid)->where('is_anonymous', false)->latest('paid_at')->limit(8)->get(['donor_name', 'category', 'paid_at'])
                ->map(fn ($d) => ['name' => Str::before($d->donor_name, ' '), 'category' => config('payments.categories')[$d->category] ?? $d->category, 'when' => $d->paid_at->diffForHumans()]),
            'testGateway' => config('payments.gateway') === 'fake',
            'campaign' => ($c = $request->query('campaign') ? FundraisingCampaign::where('slug', $request->query('campaign'))->active()->first() : null)
                ? ['slug' => $c->slug, 'title' => $c->title, 'category' => $c->category, 'matching' => $c->matching_sponsor ? "{$c->matching_sponsor} matches gifts ".rtrim(rtrim(number_format($c->matching_ratio, 2), '0'), '.').':1' : null]
                : null,
        ]);
    }

    public function store(Request $request): HttpResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('payments.categories')))],
            'amount' => ['required', 'numeric', 'min:'.config('payments.min_amount'), 'max:'.config('payments.max_amount'), 'decimal:0,2'],
            'donor_name' => ['required', 'string', 'max:160'],
            'donor_email' => ['required', 'email:rfc', 'max:255'],
            'donor_phone' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-]{7,20}$/'],
            'wants_80g' => ['boolean'],
            // Rule 114B: PAN for 80G receipts and for any gift of ₹50,000 or more.
            'pan' => [Rule::requiredIf(fn () => $request->boolean('wants_80g') || (float) $request->input('amount') >= config('payments.pan_required_from')), 'nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'address' => ['required_if:wants_80g,true', 'nullable', 'string', 'max:500'],
            'is_anonymous' => ['boolean'],
            // FCRA: only Indian-source contributions are accepted online.
            'indian_resident' => ['accepted'],
            'campaign' => ['nullable', 'string', 'max:160'],
        ], [
            'pan.required' => 'A PAN is required for an 80G receipt and for donations of ₹'.number_format(config('payments.pan_required_from')).' or more.',
            'pan.regex' => 'Enter a valid PAN, e.g. ABCDE1234F.',
            'indian_resident.accepted' => 'Online donations are accepted only from Indian citizens and residents. Please contact the alumni office about international giving.',
        ]);

        if (! empty($data['campaign'])) {
            $campaign = FundraisingCampaign::where('slug', $data['campaign'])->first();
            if (! $campaign?->isAcceptingDonations()) {
                return back()->with('error', 'That campaign isn’t accepting donations right now.');
            }
            // The campaign decides where the money goes.
            $data['category'] = $campaign->category;
            $data['fundraising_campaign_id'] = $campaign->id;
        }

        try {
            $result = $this->donations->start($data, $request->user(), $request->ip());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // An external (or test) checkout page: a full-page redirect, not an Inertia visit.
        return Inertia::location($result['redirect']);
    }

    /** The donor's browser comes back here from the hosted checkout. */
    public function return(Request $request, string $donation, PaymentGateway $gateway): Response
    {
        $d = Donation::where('reference', $donation)->firstOrFail();

        if ($d->status === Donation::PENDING) {
            try {
                $result = $gateway->verifyReturn($request, $d);
                $result['status'] === 'paid' ? $this->donations->markPaid($d, $result['payment_id']) : $this->donations->markFailed($d);
            } catch (InvalidSignature) {
                Log::channel('security')->warning('Donation return with invalid signature.', ['reference' => $d->reference, 'ip' => $request->ip()]);
            }
        }
        $d->refresh();

        return Inertia::render('Giving/Result', [
            'status' => $d->status,
            'reference' => $d->reference,
            'amount' => $d->formattedAmount(),
            'receipt' => $d->receipt_number,
            'email' => $d->donor_email,
        ]);
    }

    /** Server-to-server confirmation; authoritative even if the donor closes the tab. */
    public function webhook(Request $request, string $gateway, PaymentGateway $provider): JsonResponse
    {
        abort_unless($gateway === $provider->name(), 404);

        try {
            $event = $provider->parseWebhook($request);
        } catch (InvalidSignature) {
            Log::channel('security')->warning('Payment webhook with invalid signature.', ['gateway' => $gateway, 'ip' => $request->ip()]);

            return response()->json(['error' => 'invalid signature'], 400);
        }

        if ($event === null) {
            return response()->json(['ok' => true]);
        }

        $donation = Donation::where('gateway_order_id', $event['order_id'])->first();
        $registration = $donation ? null : EventRegistration::where('gateway_order_id', $event['order_id'])->first();

        // Replay protection: each event id is processed once.
        $fresh = DB::table('payment_events')->insertOrIgnore([
            'gateway' => $gateway, 'event_id' => $event['event_id'], 'type' => $event['type'], 'donation_id' => $donation?->id, 'created_at' => now(),
        ]);

        if ($fresh && $event['paid']) {
            if ($donation) {
                $this->donations->markPaid($donation, $event['payment_id'], $event['amount_paise']);
            } elseif ($registration) {
                app(EventRegistrationService::class)->markPaid($registration, $event['payment_id'], $event['amount_paise']);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function mine(Request $request): Response
    {
        return Inertia::render('Giving/Mine', [
            'donations' => Donation::where('user_id', $request->user()->id)->latest()->with('receipt')->get()->map(fn (Donation $d) => [
                'reference' => $d->reference, 'amount' => $d->formattedAmount(), 'category' => config('payments.categories')[$d->category] ?? $d->category,
                'status' => $d->status, 'date' => ($d->paid_at ?? $d->created_at)->format('j M Y'), 'receipt_number' => $d->receipt_number,
                'receipt_url' => $d->receipt?->url(),
            ]),
        ]);
    }
}
