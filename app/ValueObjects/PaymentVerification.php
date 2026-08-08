<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\ValueObjects;

final readonly class PaymentVerification
{
    /** @param array<string, mixed> $response */
    public function __construct(
        public string $requestId,
        public string $paymentReference,
        public string $providerStatus,
        public array $response,
        public ?string $verifiedAt,
    ) {}
}
