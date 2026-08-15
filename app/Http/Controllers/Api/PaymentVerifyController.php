<?php

// PIC: Risky Puah Kaho - Payment Module

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentApiEnvelope;
use App\Services\Payment\PaymentFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Provides hosting-payment verification to the Election module. */
final class PaymentVerifyController extends Controller
{
    public function __construct(private readonly PaymentFacade $payments) {}

    public function __invoke(Request $request): JsonResponse
    {
        $input = (array) $request->json()->all();
        $requestId = (string) $input['requestID'];

        try {
            $data = $this->payments->verifyHostingPayment([
                'paymentReference' => (string) $input['paymentReference'],
                'purpose' => (string) $input['purpose'],
                'payerId' => (int) $input['payerId'],
                'electionId' => (int) $input['electionId'],
                'expectedAmount' => (float) $input['expectedAmount'],
                'currency' => strtoupper((string) $input['currency']),
            ], $requestId);
        } catch (BusinessRuleException $exception) {
            return response()->json(
                PaymentApiEnvelope::failure($requestId, $exception->getMessage()),
                $exception->statusCode,
            );
        }

        return response()->json(PaymentApiEnvelope::success(
            $requestId,
            $data,
            'Hosting payment status retrieved.',
        ));
    }
}
