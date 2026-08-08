<?php

namespace App\Contracts;

// PIC: Goh Chun Wen - Voting Process module integration contract.
interface VotingIntegration
{
    /** @return list<array{id:int,title:string,description:string,status:string,votingPeriod:array{start:?string,end:?string}}> */
    public function openElections(string $requestId, string $timestamp): array;

    /**
     * @return array{
     *     id:int,
     *     title:string,
     *     description:string,
     *     eligibilityRules:string,
     *     status:string,
     *     votingAllowed:bool,
     *     votingPeriod:array{start:?string,end:?string},
     *     candidacies:list<array{id:int,candidateId:int,name:string,position:string,manifesto:string}>
     * }
     */
    public function election(int $electionId, string $requestId, string $timestamp): array;

    public function voterIsEligible(
        int $voterId,
        int $electionId,
        string $requestId,
        string $timestamp,
    ): bool;
}
