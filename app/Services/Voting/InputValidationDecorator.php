<?php

namespace App\Services\Voting;

use App\Models\Vote;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

// PIC: Goh Chun Wen - Decorator that validates every vote command, including non-HTTP callers.
final class InputValidationDecorator extends VoteServiceDecorator
{
    public function cast(VoteCommand $command): Vote
    {
        if ($command->voterId < 1 || $command->electionId < 1 || $command->candidateId < 1) {
            throw new InvalidVoteRequest('The vote request contains an invalid identifier.');
        }

        if (preg_match('/\A[A-Za-z0-9_-]{8,64}\z/', $command->requestId) !== 1) {
            throw new InvalidVoteRequest('The vote request identifier is invalid.');
        }

        try {
            $requestedAt = CarbonImmutable::createFromFormat(DateTimeInterface::ATOM, $command->timestamp);
        } catch (Throwable) {
            $requestedAt = null;
        }

        if (! $requestedAt instanceof CarbonImmutable
            || $requestedAt->format(DateTimeInterface::ATOM) !== $command->timestamp
            || abs(now()->diffInSeconds($requestedAt, false))
                > max(1, (int) config('voting.request_max_age_seconds', 300))) {
            throw new InvalidVoteRequest('The ballot has expired or contains an invalid timestamp. Reload it before voting.');
        }

        return $this->next->cast($command);
    }
}
