<?php

declare(strict_types=1);

namespace VoteTrust\Voting\Service;

use DateTimeImmutable;
use RuntimeException;
use VoteTrust\Voting\Model\Vote;
use VoteTrust\Voting\Model\VoteAlreadyExists;
use VoteTrust\Voting\Model\VoteStorage;

interface VoteCastingService
{
    /** @param array{voterId:int,electionId:int,candidateId:int,requestID:string,timestamp:string} $request */
    public function cast(array $request): Vote;
}

/** The core object that records an already validated vote. */
final class BasicVoteService implements VoteCastingService
{
    public function __construct(private readonly VoteStorage $votes) {}

    public function cast(array $request): Vote
    {
        $vote = new Vote(
            null,
            $request['voterId'],
            $request['electionId'],
            $request['candidateId'],
            new DateTimeImmutable(),
        );
        return $this->votes->save($vote);
    }
}

/** Base class required by the Decorator design pattern. */
abstract class VoteServiceDecorator implements VoteCastingService
{
    public function __construct(protected readonly VoteCastingService $next) {}
}

/** Prevents IDOR by checking session voter eligibility and returned ballot data. */
final class AuthorizationDecorator extends VoteServiceDecorator
{
    public function __construct(VoteCastingService $next, private readonly VotingIntegration $api)
    {
        parent::__construct($next);
    }

    public function cast(array $request): Vote
    {
        if (!$this->api->voterIsEligible(
            $request['voterId'],
            $request['electionId'],
            $request['requestID'],
            $request['timestamp'],
        )) {
            throw new VoteAccessDenied('You are not eligible to vote in this election.');
        }

        $election = $this->api->election(
            $request['electionId'],
            $request['requestID'],
            $request['timestamp'],
        );

        if ($request['electionId'] !== $election['id']) {
            throw new VoteAccessDenied('The ballot does not match the selected election.');
        }
        if (strtoupper($election['status']) !== 'VOTING_OPEN' || !$election['votingAllowed']) {
            throw new RuntimeException('This election is not open for voting.');
        }

        $candidateIsValid = false;
        foreach ($election['candidacies'] as $candidacy) {
            if ((int) $candidacy['candidateId'] === $request['candidateId']) {
                $candidateIsValid = true;
                break;
            }
        }
        if (!$candidateIsValid) {
            throw new VoteAccessDenied('The selected candidacy is not on this ballot.');
        }

        return $this->next->cast($request);
    }
}

/** Prevents two simultaneous requests from creating two votes. */
final class DuplicateVoteDecorator extends VoteServiceDecorator
{
    public function __construct(VoteCastingService $next, private readonly VoteStorage $votes)
    {
        parent::__construct($next);
    }

    public function cast(array $request): Vote
    {
        return $this->votes->locked(
            $request['voterId'],
            $request['electionId'],
            function () use ($request): Vote {
                if ($this->votes->hasVoted($request['voterId'], $request['electionId'])) {
                    throw new VoteAlreadyExists('You have already voted in this election.');
                }
                return $this->next->cast($request);
            }
        );
    }
}

final class VoteAccessDenied extends RuntimeException {}
