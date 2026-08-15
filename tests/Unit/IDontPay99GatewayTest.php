<?php

namespace Tests\Unit;

use App\Exceptions\BusinessRuleException;
use App\Models\Payment;
use App\Services\Payment\IDontPay99Gateway;
use App\States\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PIC: Risky Puah Kaho - Payment Module
 * Verifies the deterministic mock gateway's status transitions.
 */
final class IDontPay99GatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_status_confirms_a_pending_payment_by_default(): void
    {
        $payment = Payment::query()->create([
            'amount' => '100.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'provider_reference' => 'VT-HOST-PAID-001',
        ]);

        $settlement = (new IDontPay99Gateway)->resolveStatus($payment);

        self::assertSame(PaymentStatus::PAID, $settlement->status);
        self::assertSame('Paid', $payment->fresh()->status);
        self::assertNotNull($payment->fresh()->verified_at);
    }

    public function test_resolve_status_fails_a_reference_ending_in_fail(): void
    {
        $payment = Payment::query()->create([
            'amount' => '100.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'provider_reference' => 'VT-HOST-DECLINE-FAIL',
        ]);

        $settlement = (new IDontPay99Gateway)->resolveStatus($payment);

        self::assertSame(PaymentStatus::FAILED, $settlement->status);
        self::assertSame('Failed', $payment->fresh()->status);
    }

    public function test_refund_requires_a_paid_payment(): void
    {
        $payment = Payment::query()->create([
            'amount' => '100.00',
            'currency' => 'MYR',
            'status' => 'Pending',
        ]);

        $this->expectException(BusinessRuleException::class);

        (new IDontPay99Gateway)->refund($payment, 'Organizer requested a refund.');
    }

    public function test_refund_transitions_a_paid_payment(): void
    {
        $payment = Payment::query()->create([
            'amount' => '100.00',
            'currency' => 'MYR',
            'status' => 'Paid',
            'verified_at' => now(),
        ]);

        $settlement = (new IDontPay99Gateway)->refund($payment, 'Organizer requested a refund.');

        self::assertSame(PaymentStatus::REFUNDED, $settlement->status);
        self::assertSame('Refunded', $payment->fresh()->status);
        self::assertNotNull($payment->fresh()->refunded_at);
    }

    public function test_cancel_rejects_an_already_paid_payment(): void
    {
        $payment = Payment::query()->create([
            'amount' => '100.00',
            'currency' => 'MYR',
            'status' => 'Paid',
        ]);

        $this->expectException(BusinessRuleException::class);

        (new IDontPay99Gateway)->cancel($payment, 'No longer needed.');
    }

    public function test_cancel_transitions_a_pending_payment(): void
    {
        $payment = Payment::query()->create([
            'amount' => '10.00',
            'currency' => 'MYR',
            'status' => 'Pending',
        ]);

        $settlement = (new IDontPay99Gateway)->cancel($payment, 'Candidacy withdrawn.');

        self::assertSame(PaymentStatus::CANCELLED, $settlement->status);
        self::assertSame('Cancelled', $payment->fresh()->status);
        self::assertNotNull($payment->fresh()->cancelled_at);
    }
}
