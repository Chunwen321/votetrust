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
        ?string $paymentReference,
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
            'purpose' => 'ELECTION_HOSTING',
            'payerId' => $payerId,
            'electionId' => (int) $election->getKey(),
            'expectedAmount' => (float) $election->hosting_fee,
            'currency' => (string) $election->currency,
        ];
        if ($paymentReference !== null && $paymentReference !== '') {
            $payload['paymentReference'] = $paymentReference;
        }

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

        $body = $response->json();
        if ($response->status() === 404
            && is_array($body)
            && ($body['status'] ?? null) === 'F'
            && ($body['requestID'] ?? null) === $requestId) {
            return new PaymentResult(
                paid: false,
                providerStatus: 'NOT_FOUND',
                paymentReference: '',
                amount: 0,
                currency: (string) $election->currency,
                rawResponse: $body,
            );
        }

        if (! $response->successful()) {
            throw new BusinessRuleException('The payment verification service rejected the request.');
        }

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
        $electionMatches = (int) ($data['electionId'] ?? 0) === (int) $election->getKey();
        $returnedReference = (string) ($data['paymentReference'] ?? '');
        $referenceIsValid = preg_match('/^[A-Za-z0-9-]{8,64}$/', $returnedReference) === 1;
        $referenceMatches = $paymentReference === null
            || hash_equals($paymentReference, $returnedReference);
        $purposeMatches = ($data['purpose'] ?? null) === 'ELECTION_HOSTING';
        $paymentIdIsValid = is_numeric($data['paymentId'] ?? null) && (int) $data['paymentId'] > 0;

        return new PaymentResult(
            paid: ($body['status'] ?? null) === 'S'
                && ($data['paymentStatus'] ?? null) === 'CONFIRMED'
                && $amountMatches
                && $currencyMatches
                && $payerMatches
                && $electionMatches
                && $referenceIsValid
                && $referenceMatches
                && $purposeMatches
                && $paymentIdIsValid,
            providerStatus: (string) ($data['paymentStatus'] ?? 'UNKNOWN'),
            paymentReference: $returnedReference,
            amount: (float) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? ''),
            paymentId: $paymentIdIsValid ? (int) $data['paymentId'] : null,
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
