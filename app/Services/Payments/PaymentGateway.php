<?php

namespace App\Services\Payments;

use App\Contracts\Payable;
use Illuminate\Http\Request;

/**
 * A hosted-checkout payment provider. The donor is redirected to the
 * provider's page; card and UPI details never reach this application.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Create the hosted checkout and return the URL to send the donor to. Sets gateway_order_id. */
    public function checkout(Payable $payable): string;

    /**
     * Verify the signed return redirect.
     *
     * @return array{status: 'paid'|'failed', payment_id: string|null}
     *
     * @throws InvalidSignature
     */
    public function verifyReturn(Request $request, Payable $payable): array;

    /**
     * Verify and parse a server-to-server webhook.
     *
     * @return array{event_id: string, type: string, order_id: string|null, payment_id: string|null, amount_paise: int|null, paid: bool}|null null for events we ignore
     *
     * @throws InvalidSignature
     */
    public function parseWebhook(Request $request): ?array;

    public function refund(Payable $payable): void;
}
