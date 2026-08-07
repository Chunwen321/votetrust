<?php

declare(strict_types=1);

namespace VoteTrust\Voting\Model;

use DateTimeImmutable;

/** Entity class owned by the Voting Process module. */
final class Vote
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $voterId,
        public readonly int $electionId,
        public readonly int $candidateId,
        public readonly DateTimeImmutable $castAt,
    ) {}

    public function withId(int $id): self
    {
        return new self($id, $this->voterId, $this->electionId, $this->candidateId, $this->castAt);
    }
}
