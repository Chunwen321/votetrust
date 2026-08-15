<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\Services\Payment;

/** Fail-closed result of pulling candidate data from the Candidate module. */
final readonly class CandidateInfoResult
{
    public function __construct(
        public bool $available,
        public ?int $candidateId = null,
        public ?string $candidateName = null,
        public ?string $candidacyStatus = null,
    ) {}

    public static function unavailable(): self
    {
        return new self(available: false);
    }
}
