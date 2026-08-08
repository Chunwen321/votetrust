<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Services\Payment;

final readonly class PaymentResult
{
    /** @param array<string, mixed> $rawResponse */
    public function __construct(
        public bool $paid,
        public string $providerStatus,
        public string $paymentReference,
        public float $amount,
        public string $currency,
        public ?int $paymentId = null,
        public array $rawResponse = [],
    ) {}
}
