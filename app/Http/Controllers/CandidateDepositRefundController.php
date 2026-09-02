<?php

namespace App\Http\Controllers;

use App\Models\Candidacy;
use App\Services\CandidateRegistration\CandidateDepositRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Requests an automatic deposit refund for an eligible candidate. */
final class CandidateDepositRefundController extends Controller
{
    public function __invoke(
        Request $request,
        Candidacy $candidacy,
        CandidateDepositRefundService $refunds,
    ): RedirectResponse {
        $userId = (int) $request->user()?->getAuthIdentifier();
        abort_unless((int) $candidacy->user_id === $userId, 403);
        abort_unless($candidacy->payment_id !== null, 404);

        $result = $refunds->refund(
            (int) $candidacy->payment_id,
            $userId,
            (int) $candidacy->election_id,
        );

        return redirect()->route('candidate.payments.show', $candidacy->payment_id)
            ->with('success', sprintf(
                'Deposit refunded. You received %d of %d valid votes (%.2f%%).',
                $result['candidateVotes'],
                $result['totalVotes'],
                $result['votePercentage'],
            ));
    }
}
