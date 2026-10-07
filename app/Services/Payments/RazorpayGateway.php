<?php

namespace App\Services\Payments;

use App\Contracts\Payable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Razorpay Payment Links (hosted checkout). https://razorpay.com/docs/api/payments/payment-links/ */
class RazorpayGateway implements PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function name(): string
    {
        return 'razorpay';
    }

    private function http()
    {
        $key = config('payments.razorpay.key_id');
        $secret = config('payments.razorpay.key_secret');
        if (! $key || ! $secret) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        return Http::withBasicAuth($key, $secret)->acceptJson()->timeout(15)->retry(2, 500, throw: false);
    }

    public function checkout(Payable $payable): string
    {
        $response = $this->http()->post(self::API.'/payment_links', [
            'amount' => $payable->paymentAmountPaise(),
            'currency' => 'INR',
            'reference_id' => $payable->paymentReference(),
            'description' => $payable->paymentDescription(),
            'customer' => array_filter(['name' => $payable->payer()['name'], 'email' => $payable->payer()['email'], 'contact' => $payable->payer()['phone']]),
            'notify' => ['sms' => false, 'email' => false],
            'reminder_enable' => false,
            'callback_url' => $payable->paymentReturnUrl(),
            'callback_method' => 'get',
            'expire_by' => now()->addMinutes(30)->getTimestamp(),
        ]);

        if (! $response->successful() || ! $response->json('short_url')) {
            throw new RuntimeException('Could not start the payment. Please try again.');
        }

        $payable->forceFill(['gateway_order_id' => $response->json('id')])->save();

        return $response->json('short_url');
    }

    public function verifyReturn(Request $request, Payable $payable): array
    {
        $q = $request->query();
        $linkId = (string) ($q['razorpay_payment_link_id'] ?? '');
        $payload = implode('|', [$linkId, $q['razorpay_payment_link_reference_id'] ?? '', $q['razorpay_payment_link_status'] ?? '', $q['razorpay_payment_id'] ?? '']);
        $expected = hash_hmac('sha256', $payload, (string) config('payments.razorpay.key_secret'));

        if (! hash_equals($expected, (string) ($q['razorpay_signature'] ?? '')) || $linkId !== $payable->gateway_order_id) {
            throw new InvalidSignature('Payment signature mismatch.');
        }

        return [
            'status' => ($q['razorpay_payment_link_status'] ?? '') === 'paid' ? 'paid' : 'failed',
            'payment_id' => $q['razorpay_payment_id'] ?? null,
        ];
    }

    public function parseWebhook(Request $request): ?array
    {
        $secret = (string) config('payments.razorpay.webhook_secret');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($expected, (string) $request->header('X-Razorpay-Signature'))) {
            throw new InvalidSignature('Webhook signature mismatch.');
        }

        $event = $request->json('event');
        if ($event !== 'payment_link.paid') {
            return null;
        }

        return [
            'event_id' => (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $request->getContent())),
            'type' => $event,
            'order_id' => $request->json('payload.payment_link.entity.id'),
            'payment_id' => $request->json('payload.payment.entity.id'),
            'amount_paise' => $request->json('payload.payment.entity.amount'),
            'paid' => true,
        ];
    }

    public function refund(Payable $payable): void
    {
        $response = $this->http()->post(self::API."/payments/{$payable->gateway_payment_id}/refund", ['amount' => $payable->paymentAmountPaise()]);
        if (! $response->successful()) {
            throw new RuntimeException('The gateway refused the refund: '.$response->json('error.description', 'unknown error'));
        }
    }
}
