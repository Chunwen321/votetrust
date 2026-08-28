<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Contracts;

use App\Models\Election;
use App\Services\Payment\PaymentResult;
use Carbon\CarbonImmutable;

interface PaymentGateway
{
    public function verifyHostingPayment(
        Election $election,
        ?string $paymentReference,
        string $requestId,
        CarbonImmutable $requestedAt,
    ): PaymentResult;
}
