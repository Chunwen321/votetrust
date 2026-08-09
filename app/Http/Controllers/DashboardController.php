<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Election;
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
        $votingStatuses = $this->voting->statusesForVoter(
            (int) $user?->getAuthIdentifier(),
            $enrollments->pluck('election_id')->all(),
        );
        $organizedElections = Election::query()
            ->where('organizer_id', $user?->getAuthIdentifier())
            ->latest('updated_at')
            ->get();

        $readyToVote = $enrollments->filter(function ($enrollment) use ($votingStatuses): bool {
            $votingStatus = $votingStatuses[(int) $enrollment->election_id] ?? null;

            return $enrollment->status === 'APPROVED'
                && $enrollment->election_status === Election::VOTING_OPEN
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
        ]);
    }
}
