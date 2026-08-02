<?php

declare(strict_types=1);

namespace VoteTrust\Election\Services\Payment;

use DateTimeImmutable;
use VoteTrust\Election\Core\Config;
use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Models\Election;

final class HttpPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 4
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) Config::get('PAYMENT_API_URL', 'http://127.0.0.1:8081/api/v1/payments/verify'),
            (string) Config::get('PAYMENT_API_KEY', 'local-development-key'),
            (int) Config::get('PAYMENT_API_TIMEOUT', 4)
        );
    }

    public function verifyHostingPayment(
        Election $election,
        string $paymentReference,
        string $requestId,
        DateTimeImmutable $requestedAt
    ): PaymentResult {
        $payload = [
            'requestID' => $requestId,
            'timestamp' => $requestedAt->format(DATE_ATOM),
            'paymentReference' => $paymentReference,
            'purpose' => 'ELECTION_HOSTING',
            'payerId' => (int) $election->get('organizer_id'),
            'electionId' => $election->id(),
            'expectedAmount' => (float) $election->get('hosting_fee'),
            'currency' => (string) $election->get('currency'),
        ];

        $handle = curl_init($this->endpoint);
        if ($handle === false) {
            throw new BusinessRuleException('Unable to initialize the payment service connection.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        $body = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if (!is_string($body) || $httpCode < 200 || $httpCode >= 300) {
            throw new BusinessRuleException('Payment verification service is unavailable or rejected the request. ' . $curlError);
        }

        try {
            $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BusinessRuleException('Payment service returned malformed JSON.');
        }

        if (!is_array($response)
            || ($response['requestID'] ?? null) !== $requestId
            || !isset($response['status'], $response['timestamp'], $response['data'])
            || !is_array($response['data'])) {
            throw new BusinessRuleException('Payment service response does not satisfy the IFA contract.');
        }

        $data = $response['data'];
        $amountMatches = abs((float) ($data['amount'] ?? -1) - (float) $election->get('hosting_fee')) < 0.001;
        $currencyMatches = strtoupper((string) ($data['currency'] ?? '')) === strtoupper((string) $election->get('currency'));
        $payerMatches = (int) ($data['payerId'] ?? 0) === (int) $election->get('organizer_id');
        $referenceMatches = hash_equals($paymentReference, (string) ($data['paymentReference'] ?? ''));

        $paid = ($response['status'] ?? null) === 'S'
            && ($data['paymentStatus'] ?? null) === 'CONFIRMED'
            && $amountMatches
            && $currencyMatches
            && $payerMatches
            && $referenceMatches;

        return new PaymentResult(
            $paid,
            (string) ($data['paymentStatus'] ?? 'UNKNOWN'),
            (string) ($data['paymentReference'] ?? $paymentReference),
            (float) ($data['amount'] ?? 0),
            (string) ($data['currency'] ?? ''),
            isset($data['paymentId']) && is_numeric($data['paymentId']) ? (int) $data['paymentId'] : null,
            $response
        );
    }
}
