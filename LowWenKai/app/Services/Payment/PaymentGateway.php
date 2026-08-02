<?php

declare(strict_types=1);

namespace VoteTrust\Election\Services\Payment;

use DateTimeImmutable;
use VoteTrust\Election\Models\Election;

interface PaymentGateway
{
    public function verifyHostingPayment(
        Election $election,
        string $paymentReference,
        string $requestId,
        DateTimeImmutable $requestedAt
    ): PaymentResult;
}

