<?php

// PIC: Low Wen Kai — Election module HTTP consumer for User/Voter-owned enrolments.

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Throwable;

final class VoterModuleClient
{
    public function __construct(private readonly Factory $http) {}

    /**
     * @param  list<array{row:int,fullName:string,identityNumber:string}>  $voters
     * @return array{requested:int,assigned:int,skipped:int,removed:int,totalAssigned:int,unmatchedCount:int,unmatchedRows:list<array{row:int,reason:string}>}|null
     */
    public function importPrivateRoster(int $electionId, int $organizerUserId, array $voters): ?array
    {
        $baseUrl = rtrim((string) config('election.user_service.base_url', ''), '/');
        $apiKey = (string) config('election.user_service.api_key', '');
        if ($baseUrl === '' || $apiKey === '') {
            return null;
        }

        $requestId = 'elc_'.str_replace('-', '', (string) Str::uuid());
        try {
            $response = $this->http->acceptJson()
                ->withHeaders(['X-Internal-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('election.user_service.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('election.user_service.timeout_seconds', 10)))
                ->withOptions(['allow_redirects' => false])
                ->post($baseUrl.'/api/v1/voters/private-enrollments', [
                    'electionId' => $electionId,
                    'organizerUserId' => $organizerUserId,
                    'voters' => $voters,
                    'requestID' => $requestId,
                    'timestamp' => CarbonImmutable::now()->toAtomString(),
                ]);
            $body = $response->json();
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($body) || ! $this->validEnvelope($body, $requestId)) {
            return null;
        }

        $data = $body['data'] ?? null;
        if (! is_array($data)
            || (int) ($data['electionId'] ?? 0) !== $electionId
            || ! is_int($data['requested'] ?? null)
            || ! is_int($data['assigned'] ?? null)
            || ! is_int($data['skipped'] ?? null)
            || ! is_int($data['removed'] ?? null)
            || ! is_int($data['totalAssigned'] ?? null)
            || ! is_int($data['unmatchedCount'] ?? null)
            || ! is_array($data['unmatchedRows'] ?? null)) {
            return null;
        }

        $unmatchedRows = [];
        foreach ($data['unmatchedRows'] as $row) {
            if (! is_array($row) || ! is_int($row['row'] ?? null) || ! is_string($row['reason'] ?? null)) {
                return null;
            }
            $unmatchedRows[] = ['row' => $row['row'], 'reason' => $row['reason']];
        }

        return [
            'requested' => $data['requested'],
            'assigned' => $data['assigned'],
            'skipped' => $data['skipped'],
            'removed' => $data['removed'],
            'totalAssigned' => $data['totalAssigned'],
            'unmatchedCount' => $data['unmatchedCount'],
            'unmatchedRows' => $unmatchedRows,
        ];
    }

    /**
     * @return array{users:list<array{userId:int,fullName:string,identityNumber:string,verified:bool,assigned:bool}>,pagination:array{page:int,perPage:int,total:int,hasMore:bool}}|null
     */
    public function privateVoterDirectory(int $electionId, int $organizerUserId, string $search, int $page = 1): ?array
    {
        $baseUrl = rtrim((string) config('election.user_service.base_url', ''), '/');
        $apiKey = (string) config('election.user_service.api_key', '');
        if ($baseUrl === '' || $apiKey === '') {
            return null;
        }

        $requestId = 'elc_'.str_replace('-', '', (string) Str::uuid());
        try {
            $response = $this->http->acceptJson()
                ->withHeaders(['X-Internal-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('election.user_service.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('election.user_service.timeout_seconds', 10)))
                ->withOptions(['allow_redirects' => false])
                ->get($baseUrl.'/api/v1/voters/private-directory', [
                    'electionId' => $electionId,
                    'organizerUserId' => $organizerUserId,
                    'search' => trim($search),
                    'page' => max(1, $page),
                    'perPage' => 25,
                    'requestID' => $requestId,
                    'timestamp' => CarbonImmutable::now()->toAtomString(),
                ]);
            $body = $response->json();
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($body) || ! $this->validEnvelope($body, $requestId)) {
            return null;
        }

        $data = $body['data'] ?? null;
        if (! is_array($data)
            || (int) ($data['electionId'] ?? 0) !== $electionId
            || ! is_array($data['users'] ?? null)
            || ! is_array($data['pagination'] ?? null)) {
            return null;
        }

        $users = [];
        foreach ($data['users'] as $user) {
            if (! is_array($user)
                || ! is_int($user['userId'] ?? null)
                || ! is_string($user['fullName'] ?? null)
                || ! is_string($user['identityNumber'] ?? null)
                || ! is_bool($user['verified'] ?? null)
                || ! is_bool($user['assigned'] ?? null)) {
                return null;
            }
            $users[] = [
                'userId' => $user['userId'],
                'fullName' => $user['fullName'],
                'identityNumber' => $user['identityNumber'],
                'verified' => $user['verified'],
                'assigned' => $user['assigned'],
            ];
        }

        $pagination = $data['pagination'];
        if (! is_int($pagination['page'] ?? null)
            || ! is_int($pagination['perPage'] ?? null)
            || ! is_int($pagination['total'] ?? null)
            || ! is_bool($pagination['hasMore'] ?? null)) {
            return null;
        }

        return [
            'users' => $users,
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'total' => $pagination['total'],
                'hasMore' => $pagination['hasMore'],
            ],
        ];
    }

    /** @return array{userId:int,assigned:bool,changed:bool,reasonCode:string,totalAssigned:int}|null */
    public function setPrivateVoterSelection(int $electionId, int $organizerUserId, int $userId, bool $assigned): ?array
    {
        $baseUrl = rtrim((string) config('election.user_service.base_url', ''), '/');
        $apiKey = (string) config('election.user_service.api_key', '');
        if ($baseUrl === '' || $apiKey === '') {
            return null;
        }

        $requestId = 'elc_'.str_replace('-', '', (string) Str::uuid());
        try {
            $response = $this->http->acceptJson()
                ->withHeaders(['X-Internal-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('election.user_service.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('election.user_service.timeout_seconds', 10)))
                ->withOptions(['allow_redirects' => false])
                ->patch($baseUrl.'/api/v1/voters/private-enrollments/selection', [
                    'electionId' => $electionId,
                    'organizerUserId' => $organizerUserId,
                    'userId' => $userId,
                    'assigned' => $assigned,
                    'requestID' => $requestId,
                    'timestamp' => CarbonImmutable::now()->toAtomString(),
                ]);
            $body = $response->json();
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($body) || ! $this->validEnvelope($body, $requestId)) {
            return null;
        }

        $data = $body['data'] ?? null;
        if (! is_array($data)
            || (int) ($data['electionId'] ?? 0) !== $electionId
            || (int) ($data['userId'] ?? 0) !== $userId
            || ! is_bool($data['assigned'] ?? null)
            || ! is_bool($data['changed'] ?? null)
            || ! is_string($data['reasonCode'] ?? null)
            || ! is_int($data['totalAssigned'] ?? null)) {
            return null;
        }

        return [
            'userId' => $data['userId'],
            'assigned' => $data['assigned'],
            'changed' => $data['changed'],
            'reasonCode' => $data['reasonCode'],
            'totalAssigned' => $data['totalAssigned'],
        ];
    }

    /** @param array<array-key, mixed> $payload */
    private function validEnvelope(array $payload, string $requestId): bool
    {
        if (($payload['status'] ?? null) !== 'S'
            || ($payload['requestID'] ?? null) !== $requestId
            || ! is_string($payload['timestamp'] ?? null)
            || ! is_array($payload['data'] ?? null)) {
            return false;
        }

        try {
            $respondedAt = CarbonImmutable::parse($payload['timestamp']);
        } catch (Throwable) {
            return false;
        }

        return abs(CarbonImmutable::now()->diffInSeconds($respondedAt, false))
            <= max(1, (int) config('election.user_service.response_max_age_seconds', 300));
    }
}
