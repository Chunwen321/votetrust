<?php

declare(strict_types=1);

namespace VoteTrust\Election\Services\Payment;

final class PaymentResult
{
    /** @param array<string, mixed> $rawResponse */
    public function __construct(
        public readonly bool $paid,
        public readonly string $providerStatus,
        public readonly string $paymentReference,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?int $paymentId = null,
        public readonly array $rawResponse = []
    ) {
    }
}
