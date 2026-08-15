<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\Services\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls election data from the Election module's
 * `GET /api/v1/elections/summaries` provider endpoint. Fails closed: any
 * missing config, transport error, or contract mismatch yields
 * ElectionInfoResult::unavailable() rather than throwing.
 */
final class ElectionInfoClient
{
    public function __construct(private readonly Factory $http) {}

    public function fetch(int $electionId): ElectionInfoResult
    {
        $baseUrl = rtrim((string) config('payment.services.election.base_url', ''), '/');
        $apiKey = (string) config('payment.services.election.api_key', '');

        if ($baseUrl === '' || $apiKey === '' || $electionId < 1) {
            return ElectionInfoResult::unavailable();
        }

        $requestId = 'pay_'.str_replace('-', '', (string) Str::uuid());
        $requestedAt = CarbonImmutable::now();

        try {
            $response = $this->http
                ->acceptJson()
                ->withHeaders(['X-Internal-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('payment.services.election.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('payment.services.election.timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->get($baseUrl.'/api/v1/elections/summaries', [
                    'electionIds' => [$electionId],
                    'requestID' => $requestId,
                    'timestamp' => $requestedAt->toAtomString(),
                ]);

            if (! $response->successful()) {
                return ElectionInfoResult::unavailable();
            }

            $payload = $response->json();
            if (! is_array($payload) || ! $this->hasValidEnvelope($payload, $requestId)) {
                return ElectionInfoResult::unavailable();
            }

            $elections = $payload['data']['elections'] ?? null;
            $election = is_array($elections) ? ($elections[0] ?? null) : null;
            if (! is_array($election) || (int) ($election['electionId'] ?? 0) !== $electionId) {
                return ElectionInfoResult::unavailable();
            }

            return new ElectionInfoResult(
                available: true,
                electionId: (int) $election['electionId'],
                title: (string) ($election['title'] ?? ''),
                lifecycleState: (string) ($election['lifecycleState'] ?? ''),
                organizerUserId: isset($election['organizerUserId']) ? (int) $election['organizerUserId'] : null,
            );
        } catch (Throwable) {
            return ElectionInfoResult::unavailable();
        }
    }

    /** @param array<array-key, mixed> $payload */
    private function hasValidEnvelope(array $payload, string $requestId): bool
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

        $maximumAge = max(1, (int) config('payment.services.election.response_max_age_seconds', 300));

        return abs(CarbonImmutable::now()->diffInSeconds($respondedAt, false)) <= $maximumAge;
    }
}
