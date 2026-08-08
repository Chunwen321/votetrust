<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\User\VotingParticipationClient;
use App\Services\VoterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VoterController extends Controller
{
    public function __construct(
        private readonly VoterService $voters,
        private readonly VotingParticipationClient $voting,
    ) {}

    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ((bool) $user?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        $enrollments = $this->voters->enrollmentsForVoter((int) $user?->getAuthIdentifier());

        return view('voter.dashboard', [
            'user' => $user,
            'enrollments' => $enrollments,
            'votingStatuses' => $this->voting->statusesForVoter(
                (int) $user?->getAuthIdentifier(),
                $enrollments->pluck('election_id')->all(),
            ),
        ]);
    }
}
