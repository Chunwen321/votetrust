<?php

// PIC: Chew Zhexuan — User and Voter module client for the Election module API.

declare(strict_types=1);

namespace App\Services\User;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Throwable;

final class ElectionModuleClient
{
    public function __construct(private readonly Factory $http) {}

    /**
     * @param  iterable<int, int|string>  $electionIds
     * @return array<int, array{id:int,title:string,status:string,organizerUserId:?int,nominationStart:?string,nominationEnd:?string,votingStart:?string,votingEnd:?string,updatedAt:?string}>
     */
    public function summaries(iterable $electionIds): array
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', [...$electionIds])), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $payload = $this->get('/api/v1/elections/summaries', ['electionIds' => $ids]);
        $records = $payload['elections'] ?? null;
        if (! is_array($records)) {
            return [];
        }

        $summaries = [];
        foreach ($records as $record) {
            if (! is_array($record)
                || ! is_int($record['electionId'] ?? null)
                || $record['electionId'] < 1
                || ! is_string($record['title'] ?? null)
                || ! is_string($record['lifecycleState'] ?? null)) {
                continue;
            }

            $id = $record['electionId'];
            if (! in_array($id, $ids, true)) {
                continue;
            }

            $updatedAt = $record['updatedAt'] ?? null;
            $summaries[$id] = [
                'id' => $id,
                'title' => $record['title'],
                'status' => $record['lifecycleState'],
                'organizerUserId' => is_int($record['organizerUserId'] ?? null) ? $record['organizerUserId'] : null,
                'nominationStart' => $this->normaliseTimestamp($record['nominationStart'] ?? null),
                'nominationEnd' => $this->normaliseTimestamp($record['nominationEnd'] ?? null),
                'votingStart' => $this->normaliseTimestamp($record['votingStart'] ?? null),
                'votingEnd' => $this->normaliseTimestamp($record['votingEnd'] ?? null),
                'updatedAt' => is_string($updatedAt) ? $updatedAt : null,
            ];
        }

        return $summaries;
    }

    /** @return array{id:int,title:string,status:string,organizerUserId:?int,nominationStart:?string,nominationEnd:?string,votingStart:?string,votingEnd:?string,updatedAt:?string}|null */
    public function summary(int $electionId): ?array
    {
        return $this->summaries([$electionId])[$electionId] ?? null;
    }

    /**
     * @return list<array{id:int,title:string,status:string,votingAllowed:bool,eligibilityType:string,nominationStart:?string,nominationEnd:?string,votingStart:?string,votingEnd:?string}>
     */
    public function voterCatalog(): array
    {
        $payload = $this->get('/api/v1/elections/voter-catalog', []);
        $records = $payload['elections'] ?? null;
        if (! is_array($records) || ! array_is_list($records)) {
            return [];
        }

        $catalog = [];
        foreach ($records as $record) {
            $eligibility = is_array($record) ? ($record['eligibility'] ?? null) : null;
            $nominationPeriod = is_array($record) ? ($record['nominationPeriod'] ?? null) : null;
            $period = is_array($record) ? ($record['votingPeriod'] ?? null) : null;
            $type = is_array($eligibility) ? strtoupper((string) ($eligibility['type'] ?? '')) : '';
            $status = is_array($record) ? strtoupper((string) ($record['lifecycleState'] ?? '')) : '';

            if (! is_array($record)
                || ! is_int($record['electionId'] ?? null)
                || $record['electionId'] < 1
                || ! is_string($record['title'] ?? null)
                || trim($record['title']) === ''
                || ! in_array($status, ['NOMINATION_OPEN', 'VOTING_OPEN', 'CLOSED'], true)
                || ! is_bool($record['votingAllowed'] ?? null)
                || ! in_array($type, ['PUBLIC', 'PRIVATE'], true)
                || ! is_array($nominationPeriod)
                || ! array_key_exists('start', $nominationPeriod)
                || ! array_key_exists('end', $nominationPeriod)
                || ! is_array($period)
                || ! array_key_exists('start', $period)
                || ! array_key_exists('end', $period)) {
                continue;
            }

            $nominationStart = $this->normaliseTimestamp($nominationPeriod['start']);
            $nominationEnd = $this->normaliseTimestamp($nominationPeriod['end']);
            $votingStart = $this->normaliseTimestamp($period['start']);
            $votingEnd = $this->normaliseTimestamp($period['end']);
            if (($nominationPeriod['start'] !== null && $nominationStart === null)
                || ($nominationPeriod['end'] !== null && $nominationEnd === null)
                || ($period['start'] !== null && $votingStart === null)
                || ($period['end'] !== null && $votingEnd === null)) {
                continue;
            }

            $catalog[] = [
                'id' => $record['electionId'],
                'title' => trim($record['title']),
                'status' => $status,
                'votingAllowed' => $record['votingAllowed'],
                'eligibilityType' => $type,
                'nominationStart' => $nominationStart,
                'nominationEnd' => $nominationEnd,
                'votingStart' => $votingStart,
                'votingEnd' => $votingEnd,
            ];
        }

        return $catalog;
    }

    /** @return array{type:string}|null */
    public function eligibilityMode(int $electionId): ?array
    {
        if ($electionId < 1) {
            return null;
        }

        $data = $this->get('/api/v1/elections/'.$electionId.'/availability', []);
        if (($data['electionId'] ?? null) !== $electionId) {
            return null;
        }

        $rules = $data['eligibility'] ?? $data['eligibilityRules'] ?? null;
        if (! is_array($rules)) {
            return null;
        }

        $type = strtoupper((string) ($rules['type'] ?? ''));
        if (! in_array($type, ['PUBLIC', 'PRIVATE'], true)) {
            return null;
        }

        return ['type' => $type];
    }

    /**
     * @return list<array{id:int,title:string,status:string,organizerUserId:?int,updatedAt:?string}>
     */
    public function organizedBy(int $userId, int $limit = 0): array
    {
        $payload = $this->get('/api/v1/elections/organizer/'.$userId, ['limit' => $limit]);
        $records = $payload['elections'] ?? null;
        if (! is_array($records)) {
            return [];
        }

        $result = [];
        foreach ($records as $record) {
            if (! is_array($record)
                || ! is_int($record['electionId'] ?? null)
                || $record['electionId'] < 1
                || ($record['organizerUserId'] ?? null) !== $userId
                || ! is_string($record['title'] ?? null)
                || ! is_string($record['lifecycleState'] ?? null)) {
                continue;
            }

            $result[] = [
                'id' => $record['electionId'],
                'title' => $record['title'],
                'status' => $record['lifecycleState'],
                'organizerUserId' => is_int($record['organizerUserId'] ?? null) ? $record['organizerUserId'] : null,
                'updatedAt' => is_string($record['updatedAt'] ?? null) ? $record['updatedAt'] : null,
            ];
        }

        return $result;
    }

    /**
     * @return array{counts:array{elections:int,active:int,pendingApprovals:int},recentElections:list<array{id:int,title:string,status:string,organizerUserId:?int,updatedAt:?string}>,pendingApprovals:list<array{id:int,title:string,status:string,organizerUserId:?int,updatedAt:?string}>}|null
     */
    public function adminOverview(): ?array
    {
        $data = $this->get('/api/v1/elections/admin/overview', []);
        $counts = $data['counts'] ?? null;
        if (! is_array($counts) || ! is_int($counts['elections'] ?? null) || ! is_int($counts['active'] ?? null) || ! is_int($counts['pendingApprovals'] ?? null)) {
            return null;
        }

        return [
            'counts' => [
                'elections' => $counts['elections'],
                'active' => $counts['active'],
                'pendingApprovals' => $counts['pendingApprovals'],
            ],
            'recentElections' => array_values($this->normaliseRecords($data['recentElections'] ?? [])),
            'pendingApprovals' => array_values($this->normaliseRecords($data['pendingApprovals'] ?? [])),
        ];
    }

    /**
     * @return list<array{id:int,title:string,status:string,organizerUserId:?int,updatedAt:?string}> */
    private function normaliseRecords(mixed $records): array
    {
        if (! is_array($records)) {
            return [];
        }

        $result = [];
        foreach ($records as $record) {
            if (! is_array($record)
                || ! is_int($record['electionId'] ?? null)
                || $record['electionId'] < 1
                || ! is_string($record['title'] ?? null)
                || ! is_string($record['lifecycleState'] ?? null)) {
                continue;
            }
            $result[] = [
                'id' => $record['electionId'],
                'title' => $record['title'],
                'status' => $record['lifecycleState'],
                'organizerUserId' => is_int($record['organizerUserId'] ?? null) ? $record['organizerUserId'] : null,
                'updatedAt' => is_string($record['updatedAt'] ?? null) ? $record['updatedAt'] : null,
            ];
        }

        return $result;
    }

    private function normaliseTimestamp(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $timestamp = CarbonImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        } catch (Throwable) {
            return null;
        }

        return $timestamp instanceof CarbonImmutable && $timestamp->format(DateTimeInterface::ATOM) === $value
            ? $value
            : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query): ?array
    {
        $baseUrl = rtrim((string) config('votetrust-user.services.election.base_url', ''), '/');
        $apiKey = (string) config('votetrust-user.services.election.api_key', '');
        if ($baseUrl === '' || $apiKey === '') {
            return null;
        }

        $requestId = 'usr_'.str_replace('-', '', (string) Str::uuid());
        $query += ['requestID' => $requestId, 'timestamp' => CarbonImmutable::now()->toAtomString()];

        try {
            $response = $this->http->acceptJson()
                ->withHeaders(['X-Internal-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('votetrust-user.services.election.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('votetrust-user.services.election.timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->get($baseUrl.$path, $query);
            $body = $response->json();
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($body) || ! $this->validEnvelope($body, $requestId)) {
            return null;
        }

        $data = $body['data'] ?? null;

        return is_array($data) ? $data : null;
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

        return abs(CarbonImmutable::now()->diffInSeconds($respondedAt, false)) <= max(1, (int) config('votetrust-user.services.election.response_max_age_seconds', 300));
    }
}
