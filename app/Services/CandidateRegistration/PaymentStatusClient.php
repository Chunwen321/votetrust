<?php

namespace App\Services\CandidateRegistration;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Throwable;

/**
 * PIC: Wong Shun Bin
 * Consumes candidate-deposit status from the Payment module over REST/JSON.
 */
final class PaymentStatusClient
{
    public function __construct(private readonly Factory $http) {}

    public function fetch(int $paymentId, int $candidateId): CandidatePaymentStatus
    {
        $baseUrl = rtrim((string) config('candidate.services.payment.base_url', ''), '/');
        $apiKey = (string) config('candidate.services.payment.api_key', '');

        if ($baseUrl === '' || $apiKey === '' || $paymentId < 1 || $candidateId < 1) {
            return CandidatePaymentStatus::unavailable();
        }

        $requestId = 'can_'.str_replace('-', '', (string) Str::uuid());
        $requestedAt = CarbonImmutable::now();

        try {
            $response = $this->http
                ->acceptJson()
                ->withHeaders(['X-Payment-Api-Key' => $apiKey])
                ->connectTimeout(max(1, (int) config(
                    'candidate.services.payment.connect_timeout_seconds',
                    2,
                )))
                ->timeout(max(1, (int) config('candidate.services.payment.timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->get($baseUrl."/api/v1/payments/{$paymentId}/status", [
                    'candidateId' => $candidateId,
                    'requestID' => $requestId,
                    'timestamp' => $requestedAt->toAtomString(),
                ]);

            if (! $response->successful()) {
                return CandidatePaymentStatus::unavailable();
            }

            $payload = $response->json();

            if (! is_array($payload) || ! $this->hasValidEnvelope($payload, $requestId)) {
                return CandidatePaymentStatus::unavailable();
            }

            $data = $payload['data'] ?? null;
            if (! is_array($data)
                || ! is_numeric($data['paymentId'] ?? null)
                || (int) $data['paymentId'] !== $paymentId
                || ! is_numeric($data['candidateId'] ?? null)
                || (int) $data['candidateId'] !== $candidateId
                || ! is_string($data['paymentStatus'] ?? null)) {
                return CandidatePaymentStatus::unavailable();
            }

            $paymentStatus = strtoupper(trim($data['paymentStatus']));
            if (preg_match('/^[A-Z][A-Z0-9_]{0,31}$/', $paymentStatus) !== 1) {
                return CandidatePaymentStatus::unavailable();
            }

            return new CandidatePaymentStatus(
                available: true,
                status: $paymentStatus,
                paid: in_array($paymentStatus, ['CONFIRMED', 'COMPLETED', 'PAID'], true),
                message: $payload['message'],
            );
        } catch (Throwable) {
            return CandidatePaymentStatus::unavailable();
        }
    }

    /** @param array<array-key, mixed> $payload */
    private function hasValidEnvelope(array $payload, string $requestId): bool
    {
        if (($payload['status'] ?? null) !== 'S'
            || ($payload['requestID'] ?? null) !== $requestId
            || ! is_string($payload['message'] ?? null)
            || trim($payload['message']) === ''
            || ! is_string($payload['timestamp'] ?? null)
            || ! is_array($payload['data'] ?? null)) {
            return false;
        }

        try {
            $respondedAt = CarbonImmutable::parse($payload['timestamp']);
        } catch (Throwable) {
            return false;
        }

        $maximumAge = max(
            1,
            (int) config('candidate.services.payment.response_max_age_seconds', 300),
        );

        return abs(CarbonImmutable::now()->diffInSeconds($respondedAt, false)) <= $maximumAge;
    }
}
