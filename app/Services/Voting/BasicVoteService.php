<?php

namespace App\Services\Voting;

use App\Contracts\VoteCastingService;
use App\Models\Vote;

// PIC: Goh Chun Wen - Concrete Decorator component that persists an authorised vote.
final class BasicVoteService implements VoteCastingService
{
    public function cast(VoteCommand $command): Vote
    {
        return Vote::query()->create([
            'voter_id' => $command->voterId,
            'election_id' => $command->electionId,
            'candidate_id' => $command->candidateId,
            'request_id' => $command->requestId,
            'cast_at' => now(),
        ]);
    }
}
