<?php

declare(strict_types=1);

namespace VoteTrust\Election\Tests\Fakes;

use DateTimeImmutable;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\Payment\PaymentGateway;
use VoteTrust\Election\Services\Payment\PaymentResult;

final class FakePaymentGateway implements PaymentGateway
{
    public function __construct(private readonly bool $paid = true)
    {
    }

    public function verifyHostingPayment(
        Election $election,
        string $paymentReference,
        string $requestId,
        DateTimeImmutable $requestedAt
    ): PaymentResult {
        $status = $this->paid ? 'CONFIRMED' : 'PENDING';
        $response = [
            'status' => $this->paid ? 'S' : 'F',
            'requestID' => $requestId,
            'timestamp' => $requestedAt->format(DATE_ATOM),
            'data' => [
                'paymentReference' => $paymentReference,
                'paymentStatus' => $status,
                'amount' => (float) $election->get('hosting_fee'),
                'currency' => (string) $election->get('currency'),
                'payerId' => (int) $election->get('organizer_id'),
            ],
        ];

        return new PaymentResult(
            $this->paid,
            $status,
            $paymentReference,
            (float) $election->get('hosting_fee'),
            (string) $election->get('currency'),
            null,
            $response
        );
    }
}
