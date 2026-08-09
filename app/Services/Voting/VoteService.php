<?php

namespace App\Services\Voting;

use App\Contracts\VoteCastingService;
use App\Contracts\VotingIntegration;
use App\Models\User;
use App\Models\Vote;

// PIC: Goh Chun Wen - Voting Process application service and Decorator composition root.
final class VoteService
{
    private readonly VoteCastingService $pipeline;

    public function __construct(
        BasicVoteService $core,
        VotingIntegration $integration,
        VoterEligibilityAuthorizer $eligibilityAuthorizer,
    ) {
        $this->pipeline = new AuthorizationDecorator(
            new DuplicateVoteDecorator($core),
            $integration,
            $eligibilityAuthorizer,
        );
    }

    public function cast(
        User $voter,
        int $electionId,
        int $candidateId,
        string $requestId,
        string $timestamp,
    ): Vote {
        return $this->pipeline->cast(new VoteCommand(
            voterId: (int) $voter->getKey(),
            electionId: $electionId,
            candidateId: $candidateId,
            requestId: $requestId,
            timestamp: $timestamp,
        ));
    }
}
