<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HttpPaymentGateway implements PaymentGateway
{
    public function verifyHostingPayment(
        Election $election,
        string $paymentReference,
        string $requestId,
        CarbonImmutable $requestedAt,
    ): PaymentResult {
        $endpoint = trim((string) config('election.payment.url'));
        $apiKey = trim((string) config('election.payment.api_key'));
        if (! filter_var($endpoint, FILTER_VALIDATE_URL) || ! in_array(parse_url($endpoint, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new BusinessRuleException('The payment verification service endpoint is not configured safely.');
        }
        if ($apiKey === '') {
            throw new BusinessRuleException('The payment verification service credential is not configured.');
        }

        // organizer_id is the Election module's payer identifier. Payment details
        // are consumed only through the REST service, never through its database.
        $payerId = (int) $election->organizer_id;
        $payload = [
            'requestID' => $requestId,
            'timestamp' => $requestedAt->toAtomString(),
            'paymentReference' => $paymentReference,
            'purpose' => 'ELECTION_HOSTING',
            'payerId' => $payerId,
            'electionId' => (int) $election->getKey(),
            'expectedAmount' => (float) $election->hosting_fee,
            'currency' => (string) $election->currency,
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->connectTimeout((int) config('election.payment.timeout', 4))
                ->timeout((int) config('election.payment.timeout', 4))
                ->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            throw new BusinessRuleException('The payment verification service is unavailable.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new BusinessRuleException('The payment verification service rejected the request.');
        }

        $body = $response->json();
        if (! is_array($body)
            || ($body['requestID'] ?? null) !== $requestId
            || ! in_array($body['status'] ?? null, ['S', 'F'], true)
            || ! $this->hasValidResponseTimestamp($body['timestamp'] ?? null, $requestedAt)
            || ! isset($body['data'])
            || ! is_array($body['data'])) {
            throw new BusinessRuleException('The payment service response does not satisfy the integration contract.');
        }

        $data = $body['data'];
        $amountMatches = abs((float) ($data['amount'] ?? -1) - (float) $election->hosting_fee) < 0.001;
        $currencyMatches = strtoupper((string) ($data['currency'] ?? '')) === strtoupper((string) $election->currency);
        $payerMatches = (int) ($data['payerId'] ?? 0) === $payerId;
        $referenceMatches = hash_equals($paymentReference, (string) ($data['paymentReference'] ?? ''));
        $purposeMatches = ($data['purpose'] ?? null) === 'ELECTION_HOSTING';

        return new PaymentResult(
            paid: ($body['status'] ?? null) === 'S'
                && ($data['paymentStatus'] ?? null) === 'CONFIRMED'
                && $amountMatches
                && $currencyMatches
                && $payerMatches
                && $referenceMatches
                && $purposeMatches,
            providerStatus: (string) ($data['paymentStatus'] ?? 'UNKNOWN'),
            paymentReference: (string) ($data['paymentReference'] ?? $paymentReference),
            amount: (float) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? ''),
            paymentId: isset($data['paymentId']) && is_numeric($data['paymentId']) ? (int) $data['paymentId'] : null,
            rawResponse: $body,
        );
    }

    private function hasValidResponseTimestamp(mixed $value, CarbonImmutable $requestedAt): bool
    {
        if (! is_string($value)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            return false;
        }

        try {
            $respondedAt = CarbonImmutable::parse($value);
        } catch (Throwable) {
            return false;
        }

        return abs($requestedAt->diffInSeconds($respondedAt, false))
            <= (int) config('election.payment.response_clock_skew_seconds', 300);
    }
}
