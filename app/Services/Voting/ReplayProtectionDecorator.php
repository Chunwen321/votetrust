<?php

namespace App\Services\Voting;

use App\Models\Vote;

// PIC: Goh Chun Wen - Rejects a ballot request identifier after it has been used successfully.
final class ReplayProtectionDecorator extends VoteServiceDecorator
{
    public function cast(VoteCommand $command): Vote
    {
        if (Vote::query()->where('request_id', $command->requestId)->exists()) {
            throw new VoteAlreadyExists('This ballot submission has already been processed.');
        }

        return $this->next->cast($command);
    }
}
