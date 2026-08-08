<?php

namespace App\Services\Voting;

use App\Models\Vote;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// PIC: Goh Chun Wen - Decorator that serialises concurrent submissions and prevents double voting.
final class DuplicateVoteDecorator extends VoteServiceDecorator
{
    public function cast(VoteCommand $command): Vote
    {
        $lock = Cache::lock(
            "voting:cast:{$command->voterId}:{$command->electionId}",
            (int) config('voting.lock_seconds', 10),
        );

        try {
            return $lock->block(
                (int) config('voting.lock_wait_seconds', 3),
                fn (): Vote => $this->recordOnce($command),
            );
        } catch (LockTimeoutException) {
            throw new VoteAlreadyExists('A vote submission is already being processed for this election.');
        }
    }

    private function recordOnce(VoteCommand $command): Vote
    {
        try {
            return DB::transaction(function () use ($command): Vote {
                if (Vote::query()
                    ->where('voter_id', $command->voterId)
                    ->where('election_id', $command->electionId)
                    ->lockForUpdate()
                    ->exists()
                ) {
                    throw new VoteAlreadyExists('You have already voted in this election.');
                }

                return $this->next->cast($command);
            }, 3);
        } catch (QueryException $exception) {
            $message = strtoupper($exception->getMessage());
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)
                || str_contains($message, 'UNIQUE')
                || str_contains($message, 'DUPLICATE')
            ) {
                throw new VoteAlreadyExists('You have already voted in this election.', previous: $exception);
            }

            throw $exception;
        }
    }
}
