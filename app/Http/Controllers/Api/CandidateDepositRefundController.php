<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentApiEnvelope;
use App\Services\Payment\PaymentFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Refunds an eligible candidate deposit requested by the Candidate module. */
final class CandidateDepositRefundController extends Controller
{
    public function __invoke(Request $request, Payment $payment, PaymentFacade $payments): JsonResponse
    {
        $input = (array) $request->json()->all();
        $requestId = (string) $input['requestID'];
        $candidateId = (int) $input['candidateId'];
        $electionId = (int) $input['electionId'];
        $candidateVotes = (int) $input['candidateVotes'];
        $totalVotes = (int) $input['totalVotes'];

        if ((int) $payment->candidate_id !== $candidateId
            || (int) $payment->election_id !== $electionId
            || $payment->purpose !== Payment::PURPOSE_CANDIDATE_DEPOSIT) {
            return response()->json(PaymentApiEnvelope::failure($requestId, 'Candidate deposit not found.'), 404);
        }
        if ($candidateVotes * 100 <= $totalVotes * 5) {
            return response()->json(PaymentApiEnvelope::failure(
                $requestId,
                'The candidate must receive more than 5% of valid votes to receive a deposit refund.',
            ), 422);
        }

        try {
            $payments->refund($payment, 'Candidate received more than 5% of valid votes.');
        } catch (BusinessRuleException $exception) {
            return response()->json(PaymentApiEnvelope::failure($requestId, $exception->getMessage()), 422);
        }

        return response()->json(PaymentApiEnvelope::success($requestId, [
            'paymentId' => (int) $payment->getKey(),
            'candidateId' => $candidateId,
            'electionId' => $electionId,
            'candidateVotes' => $candidateVotes,
            'totalVotes' => $totalVotes,
            'votePercentage' => round(($candidateVotes / $totalVotes) * 100, 2),
            'paymentStatus' => 'REFUNDED',
        ], 'Candidate deposit refunded.'));
    }
}
