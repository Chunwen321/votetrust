<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\AdminManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ParticipantController extends Controller
{
    public function __construct(private readonly AdminManagementService $management) {}

    public function show(Election $election): View
    {
        return view('admin.participants.show', [
            'election' => $election,
            'voters' => $this->management->voters($election),
            'candidates' => $this->management->candidates($election),
            'availableVoters' => $this->management->availableVoters($election),
            'availableCandidates' => $this->management->availableCandidates($election),
            'counts' => $this->management->participantCounts($election),
        ]);
    }

    public function storeVoter(Request $request, Election $election): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:user,id'],
            'status' => ['required', Rule::in(AdminManagementService::VOTER_STATUSES)],
        ]);
        $this->management->saveVoter(
            $election,
            (int) $validated['user_id'],
            (string) $validated['status'],
            (int) $request->user()?->getAuthIdentifier()
        );

        return $this->redirectTo($election, 'voters')->with('success', 'Voter enrolment saved.');
    }

    public function updateVoter(Request $request, Election $election, int $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(AdminManagementService::VOTER_STATUSES)],
        ]);
        $this->management->changeVoterStatus(
            $election,
            $user,
            (string) $validated['status'],
            (int) $request->user()?->getAuthIdentifier()
        );

        return $this->redirectTo($election, 'voters')->with('success', 'Voter status updated.');
    }

    public function storeCandidate(Request $request, Election $election): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:user,id'],
            'status' => ['required', Rule::in(AdminManagementService::CANDIDATE_STATUSES)],
        ]);
        $this->management->saveCandidate(
            $election,
            (int) $validated['user_id'],
            (string) $validated['status']
        );

        return $this->redirectTo($election, 'candidates')->with('success', 'Candidate entry saved.');
    }

    public function updateCandidate(Request $request, Election $election, int $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(AdminManagementService::CANDIDATE_STATUSES)],
        ]);
        $this->management->changeCandidateStatus($election, $user, (string) $validated['status']);

        return $this->redirectTo($election, 'candidates')->with('success', 'Candidate status updated.');
    }

    private function redirectTo(Election $election, string $fragment): RedirectResponse
    {
        return redirect()->to(route('admin.participants.show', $election).'#'.$fragment);
    }
}
