<?php

namespace App\Contracts;

// PIC: Goh Chun Wen - Voting Process module integration contract.
interface VotingIntegration
{
    /** @return list<array{id:int,title:string,description:string,status:string,votingPeriod:array{start:?string,end:?string}}> */
    public function openElections(string $requestId, string $timestamp): array;

    /**
     * @return list<array{
     *     id:int,
     *     title:string,
     *     position:string,
     *     description:string,
     *     status:string,
     *     votingAllowed:bool,
     *     eligibility:array{
     *         type:string,
     *         minimumAge:?int,
     *         nationality:array{restricted:bool,countryCode:?string,countryName:?string,excludedCountryCode:?string}
     *     },
     *     votingPeriod:array{start:?string,end:?string},
     *     approvedCandidateCount:int
     * }>
     */
    public function voterElections(string $requestId, string $timestamp): array;

    /**
     * @return array{
     *     id:int,
     *     title:string,
     *     position:string,
     *     description:string,
     *     eligibilityRules:string,
     *     eligibility:array{
     *         type:string,
     *         minimumAge:?int,
     *         nationality:array{restricted:bool,countryCode:?string,countryName:?string,excludedCountryCode:?string}
     *     },
     *     status:string,
     *     votingAllowed:bool,
     *     nominationPeriod:array{start:?string,end:?string},
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

    /**
     * @return array{
     *     eligible:bool,
     *     reasonCode:string,
     *     dateOfBirth:?string,
     *     nationalityCountryCode:?string
     * }
     */
    public function voterEligibility(
        int $voterId,
        int $electionId,
        string $requestId,
        string $timestamp,
    ): array;
}
