<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\Contracts;

use App\Models\Payment;
use App\Services\Payment\PaymentSettlement;

/**
 * Pluggable payment-processor boundary. A real provider integration (Stripe,
 * PayPal, ...) implements this the same way IDontPay99Gateway does, so the
 * Payment Facade never depends on a concrete gateway.
 */
interface PaymentGatewayContract
{
    /** Re-derive/refresh the authoritative status of a payment from the provider. */
    public function resolveStatus(Payment $payment): PaymentSettlement;

    public function confirm(Payment $payment): PaymentSettlement;

    public function fail(Payment $payment, string $reason): PaymentSettlement;

    public function refund(Payment $payment, string $reason): PaymentSettlement;

    public function cancel(Payment $payment, string $reason): PaymentSettlement;
}
