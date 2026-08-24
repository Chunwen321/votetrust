<?php

// PIC: Chew Zhexuan — User administration client for the Candidate module API.

declare(strict_types=1);

namespace App\Services\User;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Throwable;

final class CandidateModuleClient
{
    public function __construct(private readonly Factory $http) {}

    /** @return list<array{userId:int,status:string,registeredAt:?string}> */
    public function participants(int $electionId): array
    {
        $data = $this->get('/api/v1/elections/'.$electionId.'/candidates', ['electionId' => $electionId]);
        if (($data['electionId'] ?? null) !== $electionId) {
            return [];
        }

        $candidates = $data['candidates'] ?? null;
        if (! is_array($candidates)) {
            return [];
        }

        $result = [];
        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || ! is_int($candidate['userId'] ?? null) || ! is_string($candidate['status'] ?? null)) {
                continue;
            }
            $result[] = [
                'userId' => $candidate['userId'],
                'status' => $candidate['status'],
                'registeredAt' => is_string($candidate['registeredAt'] ?? null) ? $candidate['registeredAt'] : null,
            ];
        }

        return $result;
    }

    /** @return array{candidates:int,pending:int}|null */
    public function metrics(): ?array
    {
        $data = $this->get('/api/v1/candidates/metrics');
        if (! is_int($data['candidates'] ?? null) || ! is_int($data['pending'] ?? null)) {
            return null;
        }

        return ['candidates' => $data['candidates'], 'pending' => $data['pending']];
    }

    public function saveParticipant(int $electionId, int $userId, string $status): bool
    {
        $data = $this->send('POST', '/api/v1/elections/'.$electionId.'/candidates', [
            'electionId' => $electionId,
            'userId' => $userId,
            'status' => $status,
        ]);

        return ($data['electionId'] ?? null) === $electionId
            && ($data['userId'] ?? null) === $userId
            && ($data['status'] ?? null) === $status;
    }

    public function changeParticipantStatus(int $electionId, int $userId, string $status): bool
    {
        $data = $this->send('PATCH', '/api/v1/elections/'.$electionId.'/candidates/'.$userId, [
            'electionId' => $electionId,
            'status' => $status,
        ]);

        return ($data['electionId'] ?? null) === $electionId
            && ($data['userId'] ?? null) === $userId
            && ($data['status'] ?? null) === $status;
    }

    /** @param array<string, mixed> $query
     * @return array<string, mixed>|null */
    private function get(string $path, array $query = []): ?array
    {
        $baseUrl = rtrim((string) config('votetrust-user.services.candidate.base_url', ''), '/');
        $apiKey = (string) config('votetrust-user.services.candidate.api_key', '');
        if ($baseUrl === '' || $apiKey === '') {
            return null;
        }

        $requestId = 'usr_'.str_replace('-', '', (string) Str::uuid());
        try {
            $response = $this->http->acceptJson()
                ->withHeaders(['X-Internal-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('votetrust-user.services.candidate.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('votetrust-user.services.candidate.timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->get($baseUrl.$path, $query + ['requestID' => $requestId, 'timestamp' => CarbonImmutable::now()->toAtomString()]);
            $body = $response->json();
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($body) || ! $this->validEnvelope($body, $requestId) || ! is_array($body['data'] ?? null)) {
            return null;
        }

        return $body['data'];
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>|null */
    private function send(string $method, string $path, array $payload): ?array
    {
        $baseUrl = rtrim((string) config('votetrust-user.services.candidate.base_url', ''), '/');
        $apiKey = (string) config('votetrust-user.services.candidate.api_key', '');
        if ($baseUrl === '' || $apiKey === '') {
            return null;
        }

        $requestId = 'usr_'.str_replace('-', '', (string) Str::uuid());
        $payload += ['requestID' => $requestId, 'timestamp' => CarbonImmutable::now()->toAtomString()];

        try {
            $response = $this->http->acceptJson()
                ->withHeaders(['X-Internal-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('votetrust-user.services.candidate.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('votetrust-user.services.candidate.timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->send($method, $baseUrl.$path, ['json' => $payload]);
            $body = $response->json();
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()
            || ! is_array($body)
            || ! $this->validEnvelope($body, $requestId)
            || ! is_array($body['data'] ?? null)) {
            return null;
        }

        return $body['data'];
    }

    /** @param array<array-key, mixed> $payload */
    private function validEnvelope(array $payload, string $requestId): bool
    {
        if (($payload['status'] ?? null) !== 'S'
            || ($payload['requestID'] ?? null) !== $requestId
            || ! is_string($payload['message'] ?? null)
            || ! is_string($payload['timestamp'] ?? null)) {
            return false;
        }

        try {
            $respondedAt = CarbonImmutable::parse($payload['timestamp']);
        } catch (Throwable) {
            return false;
        }

        return abs(CarbonImmutable::now()->diffInSeconds($respondedAt, false))
            <= max(1, (int) config('votetrust-user.services.candidate.response_max_age_seconds', 300));
    }
}
