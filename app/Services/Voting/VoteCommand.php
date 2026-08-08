<?php

namespace App\Services\Voting;

// PIC: Goh Chun Wen - Immutable vote command passed through the Decorator pipeline.
final readonly class VoteCommand
{
    public function __construct(
        public int $voterId,
        public int $electionId,
        public int $candidateId,
        public string $requestId,
        public string $timestamp,
    ) {}
}
