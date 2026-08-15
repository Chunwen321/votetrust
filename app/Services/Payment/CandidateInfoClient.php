<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\Services\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls candidate and organisation details from the Candidate module's
 * `GET /api/v1/candidates/{candidate}/payment-information` provider endpoint.
 * Fails closed: any missing config, transport error, or contract mismatch
 * yields CandidateInfoResult::unavailable() rather than throwing.
 */
final class CandidateInfoClient
{
    public function __construct(private readonly Factory $http) {}

    public function fetch(int $candidateId, int $electionId): CandidateInfoResult
    {
        $baseUrl = rtrim((string) config('payment.services.candidate.base_url', ''), '/');
        $apiKey = (string) config('payment.services.candidate.api_key', '');

        if ($baseUrl === '' || $apiKey === '' || $candidateId < 1 || $electionId < 1) {
            return CandidateInfoResult::unavailable();
        }

        $requestId = 'pay_'.str_replace('-', '', (string) Str::uuid());
        $requestedAt = CarbonImmutable::now();

        try {
            $response = $this->http
                ->acceptJson()
                ->withHeaders(['X-Candidate-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config('payment.services.candidate.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('payment.services.candidate.timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->get($baseUrl."/api/v1/candidates/{$candidateId}/payment-information", [
                    'electionId' => $electionId,
                    'requestID' => $requestId,
                    'timestamp' => $requestedAt->toAtomString(),
                ]);

            if (! $response->successful()) {
                return CandidateInfoResult::unavailable();
            }

            $payload = $response->json();
            if (! is_array($payload) || ! $this->hasValidEnvelope($payload, $requestId)) {
                return CandidateInfoResult::unavailable();
            }

            $data = $payload['data'];
            $candidate = $data['candidate'] ?? null;
            $organisation = $data['organisation'] ?? null;
            $candidacy = $data['candidacy'] ?? null;
            if (! is_array($candidate) || ! is_array($organisation) || ! is_array($candidacy)) {
                return CandidateInfoResult::unavailable();
            }

            return new CandidateInfoResult(
                available: true,
                candidateId: (int) ($candidate['candidateId'] ?? 0),
                candidateName: (string) ($candidate['name'] ?? ''),
                candidacyStatus: (string) ($candidacy['status'] ?? ''),
            );
        } catch (Throwable) {
            return CandidateInfoResult::unavailable();
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

        $maximumAge = max(1, (int) config('payment.services.candidate.response_max_age_seconds', 300));

        return abs(CarbonImmutable::now()->diffInSeconds($respondedAt, false)) <= $maximumAge;
    }
}
