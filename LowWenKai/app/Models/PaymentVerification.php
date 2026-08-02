<?php

declare(strict_types=1);

namespace VoteTrust\Election\Models;

final class PaymentVerification
{
    /** @param array<string, mixed> $response */
    public function __construct(
        public readonly string $requestId,
        public readonly string $paymentReference,
        public readonly string $providerStatus,
        public readonly array $response,
        public readonly ?string $verifiedAt
    ) {
    }
}
