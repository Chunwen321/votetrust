<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCandidateProfileRequest;
use App\Models\Candidacy;
use App\Models\Candidate;
use App\Services\CandidateRegistration\PaymentStatusClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PIC: Wong Shun Bin
 * Shows and updates only the authenticated user's candidate profile.
 */
final class CandidateProfileController extends Controller
{
    public function show(Request $request, PaymentStatusClient $paymentStatusClient): View
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $candidate = Candidate::query()->where('user_id', $userId)->first();
        $candidacies = Candidacy::query()
            ->with(['election', 'documents'])
            ->where('candidate_id', $userId)
            ->orderByDesc('registered_at')
            ->get();

        $paymentStatuses = [];
        foreach ($candidacies as $candidacy) {
            if ($candidacy->payment_id !== null) {
                $paymentStatuses[$candidacy->getKey()] = $paymentStatusClient->fetch(
                    (int) $candidacy->payment_id,
                    $userId,
                );
            }
        }

        return view('candidate.profile.show', compact('candidate', 'candidacies', 'paymentStatuses'));
    }

    public function update(UpdateCandidateProfileRequest $request): RedirectResponse
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $candidate = Candidate::query()->where('user_id', $userId)->first() ?? new Candidate;
        $candidate->user_id = $userId;
        $candidate->full_name = $request->string('full_name')->toString();
        $candidate->email = $request->string('email')->toString();
        $candidate->phone_number = $request->validated('phone_number');
        $candidate->save();

        return redirect()
            ->route('candidate.profile.show')
            ->with('success', 'Your candidate profile was updated.');
    }
}
