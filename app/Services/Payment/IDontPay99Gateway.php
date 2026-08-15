<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayContract;
use App\Exceptions\BusinessRuleException;
use App\Models\Payment;
use App\States\PaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Deterministic stand-in for a real payment provider ("IDontPay99" — a name
 * that can't be mistaken for a real gateway). It never makes network calls:
 * a reference ending in "-FAIL" (case-insensitive) settles as Failed,
 * everything else settles as Paid, so integration tests stay deterministic.
 */
final class IDontPay99Gateway implements PaymentGatewayContract
{
    public function resolveStatus(Payment $payment): PaymentSettlement
    {
        $current = $payment->paymentStatus();
        if ($current->isTerminal()) {
            return $this->settlementFor($payment, $current);
        }

        $reference = (string) $payment->provider_reference;

        return str_ends_with(strtoupper($reference), '-FAIL')
            ? $this->fail($payment, 'IDontPay99 declined this payment reference.')
            : $this->confirm($payment);
    }

    public function confirm(Payment $payment): PaymentSettlement
    {
        $payment->status = PaymentStatus::PAID->value;
        $payment->gateway = 'idontpay99';
        $payment->provider_reference = $payment->provider_reference ?: $this->generateReference();
        $payment->verified_at = CarbonImmutable::now();
        $payment->response_json = $this->stampResponse($payment, PaymentStatus::PAID);
        $payment->save();

        return $this->settlementFor($payment, PaymentStatus::PAID);
    }

    public function fail(Payment $payment, string $reason): PaymentSettlement
    {
        $payment->status = PaymentStatus::FAILED->value;
        $payment->gateway = 'idontpay99';
        $payment->response_json = $this->stampResponse($payment, PaymentStatus::FAILED, $reason);
        $payment->save();

        return $this->settlementFor($payment, PaymentStatus::FAILED);
    }

    public function refund(Payment $payment, string $reason): PaymentSettlement
    {
        if ($payment->paymentStatus() !== PaymentStatus::PAID) {
            throw new BusinessRuleException('Only a paid payment can be refunded.');
        }

        $payment->status = PaymentStatus::REFUNDED->value;
        $payment->refunded_at = CarbonImmutable::now();
        $payment->response_json = $this->stampResponse($payment, PaymentStatus::REFUNDED, $reason);
        $payment->save();

        return $this->settlementFor($payment, PaymentStatus::REFUNDED);
    }

    public function cancel(Payment $payment, string $reason): PaymentSettlement
    {
        if (! in_array($payment->paymentStatus(), [PaymentStatus::PENDING, PaymentStatus::FAILED], true)) {
            throw new BusinessRuleException('Only a pending or failed payment can be cancelled.');
        }

        $payment->status = PaymentStatus::CANCELLED->value;
        $payment->cancelled_at = CarbonImmutable::now();
        $payment->response_json = $this->stampResponse($payment, PaymentStatus::CANCELLED, $reason);
        $payment->save();

        return $this->settlementFor($payment, PaymentStatus::CANCELLED);
    }

    private function settlementFor(Payment $payment, PaymentStatus $status): PaymentSettlement
    {
        return new PaymentSettlement(
            status: $status,
            providerReference: (string) ($payment->provider_reference ?: $this->generateReference()),
            occurredAt: CarbonImmutable::now(),
            raw: (array) $payment->response_json,
        );
    }

    /** @return array<string, mixed> */
    private function stampResponse(Payment $payment, PaymentStatus $status, ?string $reason = null): array
    {
        return [
            'gateway' => 'idontpay99',
            'paymentStatus' => $status->providerStatus(),
            'paymentReference' => (string) $payment->provider_reference,
            'reason' => $reason,
            'settledAt' => CarbonImmutable::now()->toAtomString(),
        ];
    }

    private function generateReference(): string
    {
        return 'IDP99-'.strtoupper(Str::random(10));
    }
}
