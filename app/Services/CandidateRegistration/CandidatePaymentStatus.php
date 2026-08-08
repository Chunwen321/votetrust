<?php

namespace App\Services\CandidateRegistration;

/**
 * PIC: Wong Shun Bin
 * Immutable, fail-closed result returned by the Payment status web-service client.
 */
final readonly class CandidatePaymentStatus
{
    public function __construct(
        public bool $available,
        public string $status,
        public bool $paid,
        public string $message,
    ) {}

    public static function unavailable(): self
    {
        return new self(
            available: false,
            status: 'UNAVAILABLE',
            paid: false,
            message: 'Payment status is temporarily unavailable.',
        );
    }

    public function isPending(): bool
    {
        return $this->available && $this->status === 'PENDING';
    }

    public function displayStatus(): string
    {
        return str_replace('_', ' ', ucfirst(strtolower($this->status)));
    }
}
