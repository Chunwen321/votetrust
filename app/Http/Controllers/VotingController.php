<?php

namespace App\Http\Controllers;

use App\Contracts\VotingIntegration;
use App\Http\Requests\CastVoteRequest;
use App\Models\Vote;
use App\Services\Voting\IntegrationException;
use App\Services\Voting\InvalidVoteRequest;
use App\Services\Voting\VoteAccessDenied;
use App\Services\Voting\VoteAlreadyExists;
use App\Services\Voting\VoterEligibilityAuthorizer;
use App\Services\Voting\VoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// PIC: Goh Chun Wen - Voting Process module.
class VotingController extends Controller
{
    public function index(Request $request, VotingIntegration $integration): View
    {
        [$requestId, $timestamp] = $this->newEnvelope();

        try {
            $elections = collect($integration->openElections($requestId, $timestamp))
                ->map(function (array $election) use ($request): array {
                    $election['hasVoted'] = Vote::query()
                        ->where('voter_id', $request->user()->id)
                        ->where('election_id', $election['id'])
                        ->exists();

                    return $election;
                });
        } catch (IntegrationException) {
            return view('voting.error', [
                'message' => 'The election web service is temporarily unavailable.',
            ]);
        }

        return view('voting.index', ['elections' => $elections]);
    }

    public function ballot(
        Request $request,
        int $election,
        VotingIntegration $integration,
        VoterEligibilityAuthorizer $eligibilityAuthorizer,
    ): View {
        [$requestId, $timestamp] = $this->newEnvelope();

        try {
            $voterEligibility = $integration->voterEligibility(
                (int) $request->user()->getKey(),
                $election,
                $requestId,
                $timestamp,
            );

            $ballot = $integration->election($election, $requestId, $timestamp);
        } catch (IntegrationException) {
            return view('voting.error', [
                'message' => 'A required teammate web service is temporarily unavailable.',
            ]);
        }

        abort_unless(
            $ballot['status'] === 'VOTING_OPEN' && $ballot['votingAllowed'],
            422,
            'This election is not open for voting.',
        );
        try {
            $eligibilityAuthorizer->assertEligible($voterEligibility, $ballot['eligibility']);
        } catch (VoteAccessDenied $exception) {
            abort(403, $exception->getMessage());
        }

        return view('voting.ballot', [
            'ballot' => $ballot,
            'hasVoted' => Vote::query()
                ->where('voter_id', $request->user()->id)
                ->where('election_id', $election)
                ->exists(),
            'requestId' => $requestId,
            'timestamp' => $timestamp,
        ]);
    }

    public function cast(
        CastVoteRequest $request,
        int $election,
        VoteService $service,
    ): RedirectResponse {
        try {
            $vote = $service->cast(
                $request->user(),
                $election,
                $request->integer('candidate_id'),
                (string) $request->validated('request_id'),
                (string) $request->validated('timestamp'),
            );
        } catch (InvalidVoteRequest|VoteAlreadyExists|VoteAccessDenied $exception) {
            return back()->withErrors(['vote' => $exception->getMessage()]);
        } catch (IntegrationException) {
            return back()->withErrors([
                'vote' => 'A required teammate web service is temporarily unavailable. Please try again.',
            ]);
        }

        return redirect()->route('voting.receipt', $vote);
    }

    public function receipt(Request $request, int $vote): View
    {
        $ownedVote = Vote::query()
            ->whereKey($vote)
            ->where('voter_id', $request->user()->id)
            ->firstOrFail();

        return view('voting.success', ['vote' => $ownedVote]);
    }

    /** @return array{string,string} */
    private function newEnvelope(): array
    {
        return [
            'VOTE-'.strtoupper(bin2hex(random_bytes(12))),
            now()->toAtomString(),
        ];
    }
}
