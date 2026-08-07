<?php

declare(strict_types=1);

namespace VoteTrust\Voting\Service;

interface VotingIntegration
{
    /** @return list<array{id:int,title:string,description:string,status:string,votingPeriod:array<string,string>}> */
    public function openElections(string $requestId, string $timestamp): array;

    /**
     * @return array{
     *   id:int,title:string,description:string,eligibilityRules:string,status:string,
     *   votingAllowed:bool,votingPeriod:array<string,string>,
     *   candidacies:list<array{id:int,candidateId:int,name:string}>
     * }
     */
    public function election(int $electionId, string $requestId, string $timestamp): array;

    public function voterIsEligible(
        int $voterId,
        int $electionId,
        string $requestId,
        string $timestamp
    ): bool;
}
