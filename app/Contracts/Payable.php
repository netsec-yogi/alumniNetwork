<?php

namespace App\Contracts;

/**
 * Anything paid through the hosted checkout: donations, event fees.
 * Implementations are Eloquent models with `gateway_order_id`,
 * `gateway_payment_id` and `amount_paise` columns.
 */
interface Payable
{
    public function paymentReference(): string;

    public function paymentAmountPaise(): int;

    public function paymentDescription(): string;

    /** @return array{name: string, email: string, phone: string|null} */
    public function payer(): array;

    /** Where the provider sends the payer's browser afterwards. */
    public function paymentReturnUrl(): string;

    /** The local test-checkout page for this payable. */
    public function testCheckoutUrl(): string;
}
