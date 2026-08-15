<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\Services\Payment;

/** Fail-closed result of pulling election data from the Election module. */
final readonly class ElectionInfoResult
{
    public function __construct(
        public bool $available,
        public ?int $electionId = null,
        public ?string $title = null,
        public ?string $lifecycleState = null,
        public ?int $organizerUserId = null,
    ) {}

    public static function unavailable(): self
    {
        return new self(available: false);
    }
}
