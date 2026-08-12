<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\User\ElectionModuleClient;
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
        private readonly ElectionModuleClient $elections,
    ) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ((bool) $user?->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        $enrollments = $this->voters->enrollmentsForVoter((int) $user?->getAuthIdentifier());
        $votingStatuses = $this->voting->statusesForVoter(
            (int) $user?->getAuthIdentifier(),
            $enrollments->pluck('election_id')->all(),
        );
        $organizedElections = collect($this->elections->organizedBy((int) $user?->getAuthIdentifier()));

        $readyToVote = $enrollments->filter(function ($enrollment) use ($votingStatuses): bool {
            $votingStatus = $votingStatuses[(int) $enrollment->election_id] ?? null;

            return $enrollment->status === 'APPROVED'
                && $enrollment->election_status === 'VOTING_OPEN'
                && ($votingStatus['available'] ?? false)
                && ! ($votingStatus['hasVoted'] ?? false);
        })->count();

        return view('dashboard', [
            'user' => $user,
            'enrollments' => $enrollments,
            'votingStatuses' => $votingStatuses,
            'organizedElections' => $organizedElections,
            'overview' => [
                'votingElections' => $enrollments->count(),
                'readyToVote' => $readyToVote,
                'organizedElections' => $organizedElections->count(),
            ],
            'profileVerified' => $user?->isVerified() ?? false,
        ]);
    }
}
