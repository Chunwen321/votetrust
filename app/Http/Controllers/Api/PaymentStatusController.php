<?php

// PIC: Risky Puah Kaho - Payment Module

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentApiEnvelope;
use App\Services\Payment\PaymentFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Provides candidate-deposit payment status to the Candidate module. */
final class PaymentStatusController extends Controller
{
    public function __construct(private readonly PaymentFacade $payments) {}

    public function __invoke(Request $request, int $payment): JsonResponse
    {
        $requestId = (string) $request->query('requestID');
        $candidateId = (int) $request->integer('candidateId');

        $data = $this->payments->candidatePaymentStatus($payment, $candidateId, $requestId);

        if ($data === null) {
            return response()->json(PaymentApiEnvelope::failure(
                $requestId,
                'Payment not found for this candidate.',
                status: 'F',
            ), 404);
        }

        return response()->json(PaymentApiEnvelope::success(
            $requestId,
            $data,
            'Candidate deposit status retrieved.',
        ));
    }
}
