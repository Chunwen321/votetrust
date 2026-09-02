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
        private readonly VoterEligibilityAuthorizer $eligibilityAuthorizer,
    ) {
        parent::__construct($next);
    }

    public function cast(VoteCommand $command): Vote
    {
        $voterEligibility = $this->integration->voterEligibility(
            $command->voterId,
            $command->electionId,
            $command->requestId,
            $command->timestamp,
        );

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

        $this->eligibilityAuthorizer->assertEligible($voterEligibility, $election['eligibility']);
        $this->eligibilityAuthorizer->assertNotPublicElectionCandidate(
            $command->voterId,
            $election['eligibility'],
            $election['candidacies'],
        );

        $candidateIsApproved = collect($election['candidacies'])
            ->contains(fn (array $candidacy): bool => $candidacy['candidateId'] === $command->candidateId);

        if (! $candidateIsApproved) {
            throw new VoteAccessDenied('The selected candidate is not on this ballot.');
        }

        return $this->next->cast($command);
    }
}
