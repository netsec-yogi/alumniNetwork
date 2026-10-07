<?php

namespace App\Services\Payments;

use App\Contracts\Payable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local test checkout for development and demos: a page with "Pay" and
 * "Fail" buttons that returns a signed redirect, exactly like a real
 * provider. Refuses to run in production.
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public static function sign(string $orderId, string $status, string $paymentId): string
    {
        return hash_hmac('sha256', "{$orderId}|{$status}|{$paymentId}", (string) config('payments.fake.secret'));
    }

    public function checkout(Payable $payable): string
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The test payment gateway cannot be used in production.');
        }

        $payable->forceFill(['gateway_order_id' => 'fake_'.Str::lower(Str::random(14))])->save();

        return $payable->testCheckoutUrl();
    }

    public function verifyReturn(Request $request, Payable $payable): array
    {
        $status = (string) $request->query('status');
        $paymentId = (string) $request->query('payment_id');

        if (! hash_equals(self::sign((string) $payable->gateway_order_id, $status, $paymentId), (string) $request->query('signature'))) {
            throw new InvalidSignature('Payment signature mismatch.');
        }

        return ['status' => $status === 'paid' ? 'paid' : 'failed', 'payment_id' => $paymentId ?: null];
    }

    public function parseWebhook(Request $request): ?array
    {
        throw new InvalidSignature('The test gateway sends no webhooks.');
    }

    public function refund(Payable $payable): void
    {
        // Nothing to call.
    }
}
