<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Services\User;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Throwable;

final class VotingParticipationClient
{
    private const UNAVAILABLE_MESSAGE = 'Voting status is temporarily unavailable.';

    public function __construct(private readonly Factory $http) {}

    /**
     * @param  iterable<int, int|string>  $electionIds
     * @return array<int, array{available: bool, hasVoted: bool|null, message: string}>
     */
    public function statusesForVoter(int $voterId, iterable $electionIds): array
    {
        $statuses = [];

        foreach (array_unique(array_map('intval', [...$electionIds])) as $electionId) {
            if ($voterId < 1 || $electionId < 1) {
                continue;
            }

            $statuses[$electionId] = $this->statusForVoter($voterId, $electionId);
        }

        return $statuses;
    }

    /** @return array{available: bool, hasVoted: bool|null, message: string} */
    public function statusForVoter(int $voterId, int $electionId): array
    {
        $baseUrl = rtrim((string) config('votetrust-user.services.voting.base_url', ''), '/');
        $apiKey = (string) config('votetrust-user.services.voting.api_key', '');

        if ($baseUrl === '' || $apiKey === '' || $voterId < 1 || $electionId < 1) {
            return $this->unavailable();
        }

        $requestId = 'usr_'.str_replace('-', '', (string) Str::uuid());
        $requestedAt = CarbonImmutable::now();

        try {
            $response = $this->http
                ->acceptJson()
                ->withHeaders(['X-Internal-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('votetrust-user.services.voting.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('votetrust-user.services.voting.timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->get($baseUrl.'/api/v1/votes/status', [
                    'voterId' => $voterId,
                    'electionId' => $electionId,
                    'requestID' => $requestId,
                    'timestamp' => $requestedAt->toAtomString(),
                ]);

            if (! $response->successful()) {
                return $this->unavailable();
            }

            $payload = $response->json();
            if (! is_array($payload) || ! $this->hasValidEnvelope($payload, $requestId)) {
                return $this->unavailable();
            }

            $data = $payload['data'] ?? null;
            if (! is_array($data)
                || ($data['voterId'] ?? null) !== $voterId
                || ($data['electionId'] ?? null) !== $electionId
                || ! is_bool($data['hasVoted'] ?? null)) {
                return $this->unavailable();
            }

            return [
                'available' => true,
                'hasVoted' => $data['hasVoted'],
                'message' => (string) $payload['message'],
            ];
        } catch (Throwable) {
            return $this->unavailable();
        }
    }

    /** @param array<array-key, mixed> $payload */
    private function hasValidEnvelope(array $payload, string $requestId): bool
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

        $maximumAge = max(
            1,
            (int) config('votetrust-user.services.voting.response_max_age_seconds', 300),
        );

        return abs(CarbonImmutable::now()->diffInSeconds($respondedAt, false)) <= $maximumAge;
    }

    /** @return array{available: false, hasVoted: null, message: string} */
    private function unavailable(): array
    {
        return [
            'available' => false,
            'hasVoted' => null,
            'message' => self::UNAVAILABLE_MESSAGE,
        ];
    }
}
