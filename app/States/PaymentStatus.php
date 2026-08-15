<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\States;

enum PaymentStatus: string
{
    case PENDING = 'Pending';
    case PAID = 'Paid';
    case FAILED = 'Failed';
    case REFUNDED = 'Refunded';
    case CANCELLED = 'Cancelled';

    /** Wire-level status reported to consuming modules over the IFA envelope. */
    public function providerStatus(): string
    {
        return match ($this) {
            self::PENDING => 'PENDING',
            self::PAID => 'CONFIRMED',
            self::FAILED => 'FAILED',
            self::REFUNDED => 'REFUNDED',
            self::CANCELLED => 'CANCELLED',
        };
    }

    public function isTerminal(): bool
    {
        return $this !== self::PENDING;
    }
}
