<?php

namespace App\Services\Voting;

use App\Contracts\VotingIntegration;
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
            'eligibilityRules' => $this->describeRules($data['eligibilityRules'] ?? []),
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
            || ! is_bool($data['eligible'] ?? null)) {
            throw new IntegrationException('The User Module returned an invalid eligibility contract.');
        }

        return $data['eligible'];
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

    private function describeRules(mixed $rules): string
    {
        if (is_string($rules)) {
            return $rules;
        }

        if (! is_array($rules) || $rules === []) {
            return '';
        }

        $parts = [];
        foreach ($rules as $name => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $label = ucfirst((string) preg_replace('/(?<!^)[A-Z]/', ' $0', (string) $name));
            $parts[] = $label.': '.(is_array($value) ? implode(', ', $value) : (string) $value);
        }

        return implode('; ', $parts);
    }
}
