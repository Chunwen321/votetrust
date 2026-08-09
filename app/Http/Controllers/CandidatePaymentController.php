<?php

namespace App\Http\Controllers;

use App\Models\Candidacy;
use App\Services\CandidateRegistration\PaymentStatusClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PIC: Wong Shun Bin
 * Displays payment status only for the candidate who owns the application.
 */
final class CandidatePaymentController extends Controller
{
    public function show(Request $request, int $payment, PaymentStatusClient $paymentStatusClient): View
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $candidacy = Candidacy::query()
            ->with('election')
            ->where('payment_id', $payment)
            ->where('user_id', $userId)
            ->firstOrFail();

        $paymentStatus = $paymentStatusClient->fetch($payment, $userId);

        return view('candidate.payment.show', compact('payment', 'paymentStatus', 'candidacy'));
    }
}
