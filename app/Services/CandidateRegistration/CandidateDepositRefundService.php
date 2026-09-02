<?php

namespace App\Services\CandidateRegistration;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Checks the closed-election tally and requests an automatic Payment refund. */
final class CandidateDepositRefundService
{
    public function __construct(private readonly Factory $http) {}

    /** @return array{candidateVotes:int,totalVotes:int,votePercentage:float} */
    public function refund(int $paymentId, int $candidateId, int $electionId): array
    {
        $votingBaseUrl = rtrim((string) config('candidate.services.voting.base_url', ''), '/');
        $votingKey = (string) config('candidate.services.voting.api_key', '');
        $paymentBaseUrl = rtrim((string) config('candidate.services.payment.base_url', ''), '/');
        $paymentKey = (string) config('candidate.services.payment.api_key', '');
        if ($votingBaseUrl === '' || $votingKey === '' || $paymentBaseUrl === '' || $paymentKey === '') {
            throw ValidationException::withMessages(['refund' => 'Refund services are not configured.']);
        }

        $requestId = 'refund_'.str_replace('-', '', (string) Str::uuid());
        $requestedAt = CarbonImmutable::now();

        try {
            $tallyResponse = $this->http->acceptJson()
                ->withHeaders(['X-Internal-Api-Key' => $votingKey])
                ->connectTimeout(max(1, (int) config('candidate.services.voting.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('candidate.services.voting.timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->get($votingBaseUrl."/api/v1/elections/{$electionId}/tally", [
                    'requestID' => $requestId,
                    'timestamp' => $requestedAt->toAtomString(),
                ]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['refund' => 'Voting results are temporarily unavailable.']);
        }

        $payload = $tallyResponse->json();
        if (! $tallyResponse->successful() || ! is_array($payload) || ($payload['status'] ?? null) !== 'S'
            || ($payload['requestID'] ?? null) !== $requestId || ! is_array($payload['data'] ?? null)) {
            throw ValidationException::withMessages(['refund' => 'Refunds are available only after final election results are published.']);
        }

        $data = $payload['data'];
        $totalVotes = is_int($data['totalVotes'] ?? null) ? $data['totalVotes'] : -1;
        $tally = $data['tally'] ?? null;
        if ($totalVotes < 1 || ! is_array($tally)) {
            throw ValidationException::withMessages(['refund' => 'No valid votes are available for refund eligibility.']);
        }
        $candidateVotes = null;
        foreach ($tally as $row) {
            if (is_array($row) && (int) ($row['candidateId'] ?? 0) === $candidateId && is_int($row['votes'] ?? null)) {
                $candidateVotes = $row['votes'];
                break;
            }
        }
        if ($candidateVotes === null || $candidateVotes * 100 <= $totalVotes * 5) {
            throw ValidationException::withMessages(['refund' => 'You must receive more than 5% of valid votes to receive a deposit refund.']);
        }

        try {
            $refundResponse = $this->http->acceptJson()->asJson()
                ->withHeaders(['X-Payment-Api-Key' => $paymentKey])
                ->connectTimeout(max(1, (int) config('candidate.services.payment.connect_timeout_seconds', 2)))
                ->timeout(max(1, (int) config('candidate.services.payment.timeout_seconds', 5)))
                ->withOptions(['allow_redirects' => false])
                ->post($paymentBaseUrl."/api/v1/payments/{$paymentId}/refund", [
                    'candidateId' => $candidateId,
                    'electionId' => $electionId,
                    'candidateVotes' => $candidateVotes,
                    'totalVotes' => $totalVotes,
                    'requestID' => $requestId,
                    'timestamp' => $requestedAt->toAtomString(),
                ]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['refund' => 'The Payment refund service is temporarily unavailable.']);
        }

        $refundPayload = $refundResponse->json();
        if (! $refundResponse->successful() || ! is_array($refundPayload)
            || ($refundPayload['status'] ?? null) !== 'S'
            || ($refundPayload['requestID'] ?? null) !== $requestId
            || ($refundPayload['data']['paymentStatus'] ?? null) !== 'REFUNDED') {
            $message = is_array($refundPayload) && is_string($refundPayload['message'] ?? null)
                ? $refundPayload['message']
                : 'The deposit could not be refunded.';
            throw ValidationException::withMessages(['refund' => $message]);
        }

        return [
            'candidateVotes' => $candidateVotes,
            'totalVotes' => $totalVotes,
            'votePercentage' => round(($candidateVotes / $totalVotes) * 100, 2),
        ];
    }
}
