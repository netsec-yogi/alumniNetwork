<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Models\EventRegistration;
use App\Services\Payments\FakeGateway;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Local test checkout for any payable (PAYMENT_GATEWAY=fake, never production). */
class PaymentTestController extends Controller
{
    public function show(string $type, string $reference): Response
    {
        abort_unless(config('payments.gateway') === 'fake' && ! app()->isProduction(), 404);

        $payable = match ($type) {
            'donation' => Donation::where('reference', $reference)->where('status', Donation::PENDING)->firstOrFail(),
            'event' => EventRegistration::where('reference', $reference)->where('payment_status', 'pending')->with('event')->firstOrFail(),
            default => abort(404),
        };

        $url = function (string $status) use ($payable) {
            $pid = $status === 'paid' ? 'pay_fake_'.Str::lower(Str::random(10)) : '';
            $sep = str_contains($payable->paymentReturnUrl(), '?') ? '&' : '?';

            return $payable->paymentReturnUrl().$sep.http_build_query(['status' => $status, 'payment_id' => $pid, 'signature' => FakeGateway::sign($payable->gateway_order_id, $status, $pid)]);
        };

        return Inertia::render('Giving/FakeCheckout', [
            'amount' => '₹'.number_format($payable->paymentAmountPaise() / 100),
            'reference' => $payable->paymentReference(),
            'description' => $payable->paymentDescription(),
            'payUrl' => $url('paid'),
            'failUrl' => $url('failed'),
        ]);
    }
}
