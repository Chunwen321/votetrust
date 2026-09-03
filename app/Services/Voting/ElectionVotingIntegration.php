<?php

namespace App\Services\Voting;

use App\Contracts\VotingIntegration;
use App\Support\CountryList;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

// PIC: Goh Chun Wen - HTTP consumer for the User and Election teammate modules.
final class ElectionVotingIntegration implements VotingIntegration
{
    public function openElections(string $requestId, string $timestamp): array
    {
        $body = $this->get('election', '/api/v1/elections/available', [
            'requestID' => $requestId,
            'timestamp' => $timestamp,
        ]);

        $items = $body['data']['elections'] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new IntegrationException('The Election Module returned an invalid election list.');
        }

        return array_map(function (mixed $item): array {
            if (! is_array($item)
                || ! is_numeric($item['electionId'] ?? null)
                || (int) $item['electionId'] < 1
                || ! is_string($item['title'] ?? null)
                || ! is_string($item['lifecycleState'] ?? null)
                || ! is_array($item['votingPeriod'] ?? null)) {
                throw new IntegrationException('The Election Module returned malformed election data.');
            }

            return [
                'id' => (int) $item['electionId'],
                'title' => $item['title'],
                'description' => is_string($item['description'] ?? null) ? $item['description'] : '',
                'status' => strtoupper($item['lifecycleState']),
                'votingPeriod' => $this->period($item['votingPeriod']),
            ];
        }, $items);
    }

    public function voterElections(string $requestId, string $timestamp): array
    {
        $body = $this->get('election', '/api/v1/elections/voter-catalog', [
            'requestID' => $requestId,
            'timestamp' => $timestamp,
        ]);

        $items = $body['data']['elections'] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new IntegrationException('The Election Module returned an invalid voter election catalog.');
        }

        return array_map(function (mixed $item): array {
            if (! is_array($item)
                || ! is_int($item['electionId'] ?? null)
                || $item['electionId'] < 1
                || ! is_string($item['title'] ?? null)
                || trim($item['title']) === ''
                || ! is_string($item['positionSought'] ?? null)
                || ! is_string($item['description'] ?? null)
                || ! is_string($item['lifecycleState'] ?? null)
                || ! is_bool($item['votingAllowed'] ?? null)
                || ! is_int($item['ballotCandidateCount'] ?? null)
                || $item['ballotCandidateCount'] < 0) {
                throw new IntegrationException('The Election Module returned malformed voter catalog data.');
            }

            $status = $item['lifecycleState'];
            if (! in_array($status, ['NOMINATION_OPEN', 'NOMINATION_CLOSED', 'VOTING_OPEN', 'CLOSED'], true)
                || ($status === 'CLOSED' && $item['votingAllowed'])) {
                throw new IntegrationException('The Election Module returned an invalid voter catalog status.');
            }

            return [
                'id' => $item['electionId'],
                'title' => $item['title'],
                'position' => $item['positionSought'],
                'description' => $item['description'],
                'status' => $status,
                'votingAllowed' => $item['votingAllowed'],
                'eligibility' => $this->eligibility($item['eligibility'] ?? $item['eligibilityRules'] ?? null),
                'votingPeriod' => $this->strictPeriod($item['votingPeriod'] ?? null),
                'approvedCandidateCount' => $item['ballotCandidateCount'],
            ];
        }, $items);
    }

    public function election(int $electionId, string $requestId, string $timestamp): array
    {
        $body = $this->get('election', "/api/v1/elections/{$electionId}/availability", [
            'requestID' => $requestId,
            'timestamp' => $timestamp,
        ]);
        $data = $body['data'] ?? null;
        $eligibility = is_array($data) ? $this->eligibility($data['eligibility'] ?? $data['eligibilityRules'] ?? null) : null;

        if (! is_array($data)
            || (int) ($data['electionId'] ?? 0) !== $electionId
            || ! is_string($data['title'] ?? null)
            || ! is_string($data['positionSought'] ?? null)
            || ! is_string($data['lifecycleState'] ?? null)
            || ! is_bool($data['votingAllowed'] ?? null)
            || ! is_array($data['nominationPeriod'] ?? null)
            || ! is_array($data['votingPeriod'] ?? null)
            || ! is_array($data['ballot'] ?? null)
            || ! array_is_list($data['ballot'])) {
            throw new IntegrationException('The Election Module returned an invalid ballot contract.');
        }

        $candidacies = array_map(function (mixed $candidate): array {
            if (! is_array($candidate)
                || ! is_numeric($candidate['id'] ?? null)
                || ! is_numeric($candidate['candidateId'] ?? null)
                || ! is_string($candidate['name'] ?? null)) {
                throw new IntegrationException('The Election Module returned malformed ballot data.');
            }

            foreach (['manifesto', 'introduction', 'qualifications', 'reasonForCandidacy', 'photoUrl'] as $field) {
                if (array_key_exists($field, $candidate)
                    && $candidate[$field] !== null
                    && ! is_string($candidate[$field])) {
                    throw new IntegrationException('The Election Module returned malformed candidate profile data.');
                }
            }

            $photoUrl = $candidate['photoUrl'] ?? null;
            if (is_string($photoUrl) && filter_var($photoUrl, FILTER_VALIDATE_URL) === false) {
                throw new IntegrationException('The Election Module returned an invalid candidate photo URL.');
            }

            return [
                'id' => (int) $candidate['id'],
                'candidateId' => (int) $candidate['candidateId'],
                'name' => $candidate['name'],
                'position' => is_string($candidate['position'] ?? null) ? $candidate['position'] : '',
                'manifesto' => is_string($candidate['manifesto'] ?? null) ? $candidate['manifesto'] : '',
                'introduction' => is_string($candidate['introduction'] ?? null) ? $candidate['introduction'] : null,
                'qualifications' => is_string($candidate['qualifications'] ?? null) ? $candidate['qualifications'] : null,
                'reasonForCandidacy' => is_string($candidate['reasonForCandidacy'] ?? null) ? $candidate['reasonForCandidacy'] : null,
                'photoUrl' => is_string($photoUrl) ? $photoUrl : null,
            ];
        }, $data['ballot']);

        return [
            'id' => $electionId,
            'title' => $data['title'],
            'position' => $data['positionSought'],
            'description' => is_string($data['description'] ?? null) ? $data['description'] : '',
            'eligibilityRules' => $this->describeRules($eligibility),
            'eligibility' => $eligibility,
            'status' => strtoupper($data['lifecycleState']),
            'votingAllowed' => $data['votingAllowed'],
            'nominationPeriod' => $this->strictPeriod($data['nominationPeriod']),
            'votingPeriod' => $this->strictPeriod($data['votingPeriod']),
            'candidacies' => $candidacies,
        ];
    }

    public function voterIsEligible(
        int $voterId,
        int $electionId,
        string $requestId,
        string $timestamp,
    ): bool {
        return $this->voterEligibility($voterId, $electionId, $requestId, $timestamp)['eligible'];
    }

    public function voterEligibility(
        int $voterId,
        int $electionId,
        string $requestId,
        string $timestamp,
    ): array {
        $body = $this->get('user', '/api/v1/voters/eligibility', [
            'voterId' => $voterId,
            'electionId' => $electionId,
            'requestID' => $requestId,
            'timestamp' => $timestamp,
        ]);
        $data = $body['data'] ?? null;

        if (! is_array($data)
            || (int) ($data['voterId'] ?? 0) !== $voterId
            || (int) ($data['electionId'] ?? 0) !== $electionId
            || ! is_bool($data['eligible'] ?? null)
            || ! is_string($data['reasonCode'] ?? null)) {
            throw new IntegrationException('The User Module returned an invalid eligibility contract.');
        }

        $identity = $data['identity'] ?? [];
        if (! is_array($identity)) {
            throw new IntegrationException('The User Module returned invalid voter identity data.');
        }
        $nationality = $identity['nationality'] ?? [];
        if (! is_array($nationality)) {
            throw new IntegrationException('The User Module returned invalid voter nationality data.');
        }

        $dateOfBirth = $identity['dateOfBirth'] ?? null;
        if ($dateOfBirth !== null && ! $this->isDate((string) $dateOfBirth)) {
            throw new IntegrationException('The User Module returned an invalid voter birth date.');
        }

        $countryCode = $nationality['countryCode'] ?? null;
        if ($countryCode !== null) {
            $countryCode = strtoupper((string) $countryCode);
            if (! CountryList::isValidCode($countryCode)) {
                throw new IntegrationException('The User Module returned an invalid voter nationality code.');
            }
        }

        return [
            'eligible' => $data['eligible'],
            'reasonCode' => $data['reasonCode'],
            'dateOfBirth' => $dateOfBirth,
            'nationalityCountryCode' => $countryCode,
        ];
    }

    /** @param array<string, int|string> $query
     * @return array<string, mixed>
     */
    private function get(string $service, string $path, array $query): array
    {
        $baseUrl = rtrim((string) config("voting.services.{$service}.base_url"), '/');
        $apiKey = trim((string) config("voting.services.{$service}.api_key"));
        if ($baseUrl === '' || $apiKey === '') {
            throw new IntegrationException("The {$service} web service is not configured.");
        }

        try {
            $response = $this->client($service, $apiKey)->get($baseUrl.$path, $query);
        } catch (ConnectionException $exception) {
            throw new IntegrationException("The {$service} web service is unavailable.", previous: $exception);
        }

        if (! $response->successful()) {
            throw new IntegrationException("The {$service} web service rejected the request.");
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new IntegrationException("The {$service} web service did not return JSON.");
        }

        $requestId = (string) ($query['requestID'] ?? '');
        if (($body['status'] ?? null) !== 'S'
            || ! is_string($body['requestID'] ?? null)
            || ! hash_equals($requestId, $body['requestID'])
            || ! is_string($body['timestamp'] ?? null)
            || ! is_array($body['data'] ?? null)) {
            throw new IntegrationException("The {$service} response does not satisfy the IFA envelope.");
        }

        try {
            $generatedAt = CarbonImmutable::parse($body['timestamp']);
        } catch (\Throwable $exception) {
            throw new IntegrationException("The {$service} response timestamp is invalid.", previous: $exception);
        }

        if (abs(now()->diffInSeconds($generatedAt, false))
            > (int) config('voting.response_max_age_seconds', 300)) {
            throw new IntegrationException("The {$service} response timestamp is stale.");
        }

        return $body;
    }

    private function client(string $service, string $apiKey): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['X-Internal-Api-Key' => $apiKey])
            ->connectTimeout((int) config("voting.services.{$service}.connect_timeout_seconds", 2))
            ->timeout((int) config("voting.services.{$service}.timeout_seconds", 5));
    }

    /** @param array<string, mixed> $period
     * @return array{start:?string,end:?string}
     */
    private function period(array $period): array
    {
        return [
            'start' => is_string($period['start'] ?? null) ? $period['start'] : null,
            'end' => is_string($period['end'] ?? null) ? $period['end'] : null,
        ];
    }

    /** @return array{start:?string,end:?string} */
    private function strictPeriod(mixed $period): array
    {
        if (! is_array($period)
            || ! array_key_exists('start', $period)
            || ! array_key_exists('end', $period)) {
            throw new IntegrationException('The Election Module returned an invalid election period.');
        }

        foreach (['start', 'end'] as $field) {
            $value = $period[$field];
            if ($value === null) {
                continue;
            }
            if (! is_string($value)) {
                throw new IntegrationException('The Election Module returned an invalid election period.');
            }

            try {
                $parsed = CarbonImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
            } catch (\Throwable) {
                $parsed = false;
            }

            if ($parsed === false || $parsed->format(DateTimeInterface::ATOM) !== $value) {
                throw new IntegrationException('The Election Module returned an invalid election period.');
            }
        }

        return [
            'start' => $period['start'],
            'end' => $period['end'],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     minimumAge:?int,
     *     nationality:array{restricted:bool,countryCode:?string,countryName:?string,excludedCountryCode:?string}
     * }
     */
    private function eligibility(mixed $rules): array
    {
        if (! is_array($rules)) {
            throw new IntegrationException('The Election Module returned invalid eligibility rules.');
        }

        $type = strtoupper((string) ($rules['type'] ?? ''));
        if (! in_array($type, ['PUBLIC', 'PRIVATE'], true)) {
            throw new IntegrationException('The Election Module returned an invalid eligibility type.');
        }

        $minimumAge = $rules['minimumAge'] ?? null;
        if ($minimumAge !== null && (! is_int($minimumAge) || $minimumAge < 0 || $minimumAge > 130)) {
            throw new IntegrationException('The Election Module returned an invalid minimum age.');
        }

        $nationality = $rules['nationality'] ?? null;
        if (! is_array($nationality) || ! is_bool($nationality['restricted'] ?? null)) {
            throw new IntegrationException('The Election Module returned invalid nationality rules.');
        }

        $countryCode = $nationality['countryCode'] ?? null;
        $excludedCountryCode = $nationality['excludedCountryCode'] ?? null;
        if ($countryCode !== null && ! CountryList::isValidCode((string) $countryCode)) {
            throw new IntegrationException('The Election Module returned an invalid nationality country code.');
        }
        if ($excludedCountryCode !== null && ! CountryList::isValidCode((string) $excludedCountryCode)) {
            throw new IntegrationException('The Election Module returned an invalid excluded country code.');
        }
        if ($nationality['restricted'] && $countryCode === null && $excludedCountryCode === null) {
            throw new IntegrationException('The Election Module returned an incomplete nationality restriction.');
        }
        if (! $nationality['restricted'] && ($countryCode !== null || $excludedCountryCode !== null)) {
            throw new IntegrationException('The Election Module returned inconsistent nationality rules.');
        }

        $countryName = $nationality['countryName'] ?? null;
        if ($countryName !== null && ! is_string($countryName)) {
            throw new IntegrationException('The Election Module returned an invalid country name.');
        }

        if ($type === 'PRIVATE') {
            $minimumAge = null;
            $countryCode = null;
            $countryName = null;
            $excludedCountryCode = null;
        }

        return [
            'type' => $type,
            'minimumAge' => $minimumAge,
            'nationality' => [
                'restricted' => $type === 'PUBLIC' && $nationality['restricted'],
                'countryCode' => $countryCode,
                'countryName' => $countryName,
                'excludedCountryCode' => $excludedCountryCode,
            ],
        ];
    }

    /** @param array<string, mixed> $rules */
    private function describeRules(array $rules): string
    {
        if ($rules['type'] === 'PRIVATE') {
            return 'Private election - approved voter list only';
        }

        $parts = [];
        if ($rules['minimumAge'] !== null) {
            $parts[] = 'Age '.$rules['minimumAge'].' or older';
        }
        if ($rules['nationality']['countryName'] !== null) {
            $parts[] = 'Nationality: '.$rules['nationality']['countryName'];
        }

        return $parts === [] ? 'Public election' : 'Public election | '.implode(' | ', $parts);
    }

    private function isDate(string $value): bool
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return false;
        }

        return $date !== false && $date->format('Y-m-d') === $value && ! $date->isFuture();
    }
}
