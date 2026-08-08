<?php

namespace App\Services\Voting;

use App\Contracts\VoteCastingService;
use App\Contracts\VotingIntegration;
use App\Models\Vote;

// PIC: Goh Chun Wen - Decorator that prevents IDOR and unauthorised ballot access.
final class AuthorizationDecorator extends VoteServiceDecorator
{
    public function __construct(
        VoteCastingService $next,
        private readonly VotingIntegration $integration,
    ) {
        parent::__construct($next);
    }

    public function cast(VoteCommand $command): Vote
    {
        if (! $this->integration->voterIsEligible(
            $command->voterId,
            $command->electionId,
            $command->requestId,
            $command->timestamp,
        )) {
            throw new VoteAccessDenied('You are not eligible to vote in this election.');
        }

        $election = $this->integration->election(
            $command->electionId,
            $command->requestId,
            $command->timestamp,
        );

        if ($election['id'] !== $command->electionId) {
            throw new VoteAccessDenied('The ballot does not match the selected election.');
        }

        if ($election['status'] !== 'VOTING_OPEN' || ! $election['votingAllowed']) {
            throw new VoteAccessDenied('This election is not open for voting.');
        }

        $candidateIsApproved = collect($election['candidacies'])
            ->contains(fn (array $candidacy): bool => $candidacy['candidateId'] === $command->candidateId);

        if (! $candidateIsApproved) {
            throw new VoteAccessDenied('The selected candidate is not on this ballot.');
        }

        return $this->next->cast($command);
    }
}
