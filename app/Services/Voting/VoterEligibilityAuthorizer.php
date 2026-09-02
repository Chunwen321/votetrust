<?php

namespace App\Services\Voting;

use Carbon\CarbonImmutable;

// PIC: Goh Chun Wen, Low Wen Kai - Server-side age and nationality authorization.
final class VoterEligibilityAuthorizer
{
    /**
     * @param  array{eligible:bool,reasonCode:string,dateOfBirth:?string,nationalityCountryCode:?string}  $voter
     * @param  array{type:string,minimumAge:?int,nationality:array{restricted:bool,countryCode:?string,countryName:?string,excludedCountryCode:?string}}  $rules
     */
    public function assertEligible(array $voter, array $rules): void
    {
        if (! $voter['eligible']) {
            throw new VoteAccessDenied('You are not eligible to vote in this election.');
        }

        // User/Voter has already confirmed the approved roster entry for a
        // private election. Public elections additionally apply the Election
        // Module's published age and nationality rules below.
        if ($rules['type'] === 'PRIVATE') {
            return;
        }

        $minimumAge = $rules['minimumAge'];
        if ($minimumAge !== null) {
            if ($voter['dateOfBirth'] === null) {
                throw new VoteAccessDenied('Your verified date of birth is unavailable.');
            }

            $dateOfBirth = CarbonImmutable::createFromFormat('!Y-m-d', $voter['dateOfBirth']);
            if ($dateOfBirth === false || $dateOfBirth->diffInYears(CarbonImmutable::now()) < $minimumAge) {
                throw new VoteAccessDenied('You do not meet the minimum age for this election.');
            }
        }

        $nationality = $rules['nationality'];
        if (! $nationality['restricted']) {
            return;
        }

        $voterCountryCode = $voter['nationalityCountryCode'];
        if ($voterCountryCode === null) {
            throw new VoteAccessDenied('Your verified nationality is unavailable.');
        }

        if ($nationality['countryCode'] !== null && $voterCountryCode !== $nationality['countryCode']) {
            throw new VoteAccessDenied('You do not meet the nationality requirement for this election.');
        }

        if ($nationality['excludedCountryCode'] !== null && $voterCountryCode === $nationality['excludedCountryCode']) {
            throw new VoteAccessDenied('You do not meet the nationality requirement for this election.');
        }
    }

    /**
     * Candidates on a public-election ballot must not also participate as voters.
     *
     * @param  array<string, mixed>  $rules
     * @param  list<array<string, mixed>>  $candidacies
     */
    public function assertNotPublicElectionCandidate(int $voterId, array $rules, array $candidacies): void
    {
        if (($rules['type'] ?? null) !== 'PUBLIC') {
            return;
        }

        $isCandidate = collect($candidacies)->contains(
            static fn (array $candidacy): bool => (int) ($candidacy['candidateId'] ?? 0) === $voterId,
        );

        if ($isCandidate) {
            throw new VoteAccessDenied('Candidates cannot vote in the public election in which they are registered.');
        }
    }
}
