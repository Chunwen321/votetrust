<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\Services\Payment;

use App\States\PaymentStatus;
use Carbon\CarbonImmutable;

final readonly class PaymentSettlement
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public PaymentStatus $status,
        public string $providerReference,
        public CarbonImmutable $occurredAt,
        public array $raw = [],
    ) {}
}
