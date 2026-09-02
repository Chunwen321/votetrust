<?php

namespace App\Http\Controllers;

use App\Models\Candidacy;
use App\Services\CandidateRegistration\PaymentStatusClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Provides an owned application history and status workspace for candidates.
 */
final class CandidateApplicationController extends Controller
{
    public function index(Request $request, PaymentStatusClient $paymentStatusClient): View
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $applications = Candidacy::query()
            ->with('election')
            ->where('user_id', $userId)
            ->orderByDesc('registered_at')
            ->get();

        $paymentStatuses = [];
        foreach ($applications as $application) {
            if ($application->payment_id !== null) {
                $paymentStatuses[$application->getKey()] = $paymentStatusClient->fetch(
                    (int) $application->payment_id,
                    $userId,
                );
            }
        }

        return view('candidate.applications.index', [
            'applications' => $applications,
            'paymentStatuses' => $paymentStatuses,
        ]);
    }

    public function show(
        Request $request,
        Candidacy $candidacy,
        PaymentStatusClient $paymentStatusClient,
    ): View {
        $userId = (int) $request->user()?->getAuthIdentifier();
        abort_unless((int) $candidacy->user_id === $userId, 403);

        $candidacy->load('election');
        $paymentStatus = $candidacy->payment_id !== null
            ? $paymentStatusClient->fetch((int) $candidacy->payment_id, $userId)
            : null;

        return view('candidate.applications.show', [
            'application' => $candidacy,
            'paymentStatus' => $paymentStatus,
        ]);
    }
}
