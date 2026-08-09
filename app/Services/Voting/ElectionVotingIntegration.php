<?php

namespace App\Services\Voting;

use App\Contracts\VotingIntegration;
use App\Support\CountryList;
use Carbon\CarbonImmutable;
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
            || ! is_string($data['lifecycleState'] ?? null)
            || ! is_bool($data['votingAllowed'] ?? null)
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

            return [
                'id' => (int) $candidate['id'],
                'candidateId' => (int) $candidate['candidateId'],
                'name' => $candidate['name'],
                'position' => is_string($candidate['position'] ?? null) ? $candidate['position'] : '',
                'manifesto' => is_string($candidate['manifesto'] ?? null) ? $candidate['manifesto'] : '',
            ];
        }, $data['ballot']);

        return [
            'id' => $electionId,
            'title' => $data['title'],
            'description' => is_string($data['description'] ?? null) ? $data['description'] : '',
            'eligibilityRules' => $this->describeRules($eligibility),
            'eligibility' => $eligibility,
            'status' => strtoupper($data['lifecycleState']),
            'votingAllowed' => $data['votingAllowed'],
            'votingPeriod' => $this->period($data['votingPeriod']),
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
        if ($baseUrl === '') {
            throw new IntegrationException("The {$service} web service is not configured.");
        }

        try {
            $response = $this->client($service)->get($baseUrl.$path, $query);
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

    private function client(string $service): PendingRequest
    {
        $request = Http::acceptJson()
            ->connectTimeout((int) config("voting.services.{$service}.connect_timeout_seconds", 2))
            ->timeout((int) config("voting.services.{$service}.timeout_seconds", 5));

        $apiKey = (string) config("voting.services.{$service}.api_key");
        if ($apiKey !== '') {
            $request = $request->withHeaders(['X-Internal-Api-Key' => $apiKey]);
        }

        return $request;
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
        if (! in_array($type, ['PUBLIC', 'RESTRICTED'], true)) {
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

        if ($type === 'PUBLIC') {
            $minimumAge = null;
            $countryCode = null;
            $countryName = null;
            $excludedCountryCode = null;
        }

        return [
            'type' => $type,
            'minimumAge' => $minimumAge,
            'nationality' => [
                'restricted' => $type === 'RESTRICTED' && $nationality['restricted'],
                'countryCode' => $countryCode,
                'countryName' => $countryName,
                'excludedCountryCode' => $excludedCountryCode,
            ],
        ];
    }

    /** @param array<string, mixed> $rules */
    private function describeRules(array $rules): string
    {
        if ($rules['type'] === 'PUBLIC') {
            return 'Public election - no voter restrictions';
        }

        $parts = [];
        if ($rules['minimumAge'] !== null) {
            $parts[] = 'Age '.$rules['minimumAge'].' or older';
        }
        if ($rules['nationality']['countryName'] !== null) {
            $parts[] = 'Nationality: '.$rules['nationality']['countryName'];
        }

        return $parts === [] ? 'Restricted election' : implode(' | ', $parts);
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
