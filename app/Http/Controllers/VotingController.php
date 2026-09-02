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
use App\Services\Voting\VoteTallyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

// PIC: Goh Chun Wen - Voting Process module.
class VotingController extends Controller
{
    public function results(
        Request $request,
        int $election,
        VotingIntegration $integration,
        VoteTallyService $tallyService,
    ): Response|View {
        [$requestId, $timestamp] = $this->newEnvelope();

        try {
            $details = $integration->election($election, $requestId, $timestamp);
        } catch (IntegrationException) {
            return response()->view('voting.error', [
                'message' => 'A required teammate web service is temporarily unavailable.',
            ], 503);
        }

        abort_unless($details['status'] === 'CLOSED', 404);

        $tally = $tallyService->tally($election, $details['candidacies']);
        $results = $tally['tally'];
        usort($results, static fn (array $left, array $right): int => $right['votes'] <=> $left['votes']
                ?: strcasecmp($left['candidateName'], $right['candidateName'])
        );

        $totalVotes = $tally['totalVotes'];
        $highestVoteCount = $totalVotes > 0 ? (int) ($results[0]['votes'] ?? 0) : 0;
        $winnerCount = $highestVoteCount > 0
            ? count(array_filter($results, static fn (array $result): bool => $result['votes'] === $highestVoteCount))
            : 0;

        $results = array_map(static fn (array $result): array => [
            ...$result,
            'percentage' => $totalVotes > 0 ? round(($result['votes'] / $totalVotes) * 100, 1) : 0.0,
            'isWinner' => $highestVoteCount > 0 && $result['votes'] === $highestVoteCount,
        ], $results);

        $userId = $request->user()?->getAuthIdentifier();
        $hasVoted = $userId !== null && Vote::query()
            ->where('voter_id', $userId)
            ->where('election_id', $election)
            ->exists();

        return response()
            ->view('voting.results', [
                'election' => $details,
                'results' => $results,
                'totalVotes' => $totalVotes,
                'winnerCount' => $winnerCount,
                'hasVoted' => $hasVoted,
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0');
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
            $eligibilityAuthorizer->assertNotPublicElectionCandidate(
                (int) $request->user()->getKey(),
                $ballot['eligibility'],
                $ballot['candidacies'],
            );
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
        } catch (VoteAlreadyExists) {
            $recordedVote = Vote::query()
                ->where('voter_id', $request->user()->id)
                ->where('election_id', $election)
                ->first();

            if ($recordedVote instanceof Vote) {
                return redirect()
                    ->route('voting.ballot', $election)
                    ->with('duplicate_vote', true);
            }

            return redirect()
                ->route('voting.ballot', $election)
                ->with('notice', 'Your vote is not confirmed yet. Please wait a moment, then refresh the ballot.');
        } catch (InvalidVoteRequest|VoteAccessDenied $exception) {
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
