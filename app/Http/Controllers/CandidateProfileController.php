<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCandidateProfileRequest;
use App\Models\Candidacy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PIC: Wong Shun Bin
 * Shows and updates the authenticated user's candidate contact profile.
 */
final class CandidateProfileController extends Controller
{
    public function show(Request $request): View
    {
        $userId = (int) $request->user()?->getAuthIdentifier();

        return view('candidate.profile.show', [
            'user' => $request->user(),
            'applicationCount' => Candidacy::query()->where('user_id', $userId)->count(),
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
