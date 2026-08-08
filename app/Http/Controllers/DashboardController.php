<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\User\VotingParticipationClient;
use App\Services\VoterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly VoterService $voters,
        private readonly VotingParticipationClient $voting,
    ) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ((bool) $user?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        $enrollments = $this->voters->enrollmentsForVoter((int) $user?->getAuthIdentifier());
        $recentEnrollments = $enrollments->take(3);

        return view('dashboard', [
            'user' => $user,
            'enrollments' => $enrollments,
            'counts' => $this->voters->statusCountsForVoter((int) $user?->getAuthIdentifier()),
            'votingStatuses' => $this->voting->statusesForVoter(
                (int) $user?->getAuthIdentifier(),
                $recentEnrollments->pluck('election_id')->all(),
            ),
        ]);
    }
}
