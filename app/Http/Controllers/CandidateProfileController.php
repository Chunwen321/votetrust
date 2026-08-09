<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCandidateProfileRequest;
use App\Models\Candidacy;
use App\Services\CandidateRegistration\PaymentStatusClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PIC: Wong Shun Bin
 * Shows and updates the authenticated user's profile alongside candidacies.
 */
final class CandidateProfileController extends Controller
{
    public function show(Request $request, PaymentStatusClient $paymentStatusClient): View
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $candidacies = Candidacy::query()
            ->with(['election', 'documents'])
            ->where('user_id', $userId)
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

        return view('candidate.profile.show', [
            'user' => $request->user(),
            'candidacies' => $candidacies,
            'paymentStatuses' => $paymentStatuses,
        ]);
    }

    public function update(UpdateCandidateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->full_name = $request->string('full_name')->toString();
        $user->email = $request->string('email')->toString();
        $user->phone_number = $request->validated('phone_number');
        $user->save();

        return redirect()
            ->route('candidate.profile.show')
            ->with('success', 'Your user profile was updated.');
    }
}
