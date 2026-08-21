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
    public function index(Request $request, VotingIntegration $integration): View
    {
        [$requestId, $timestamp] = $this->newEnvelope();

        try {
            $elections = collect($integration->voterElections($requestId, $timestamp))
                ->filter(function (array $election) use ($integration, $request): bool {
                    if (data_get($election, 'eligibility.type') !== 'PRIVATE') {
                        return true;
                    }

                    [$eligibilityRequestId, $eligibilityTimestamp] = $this->newEnvelope();
                    $eligibility = $integration->voterEligibility(
                        (int) $request->user()->getKey(),
                        (int) $election['id'],
                        $eligibilityRequestId,
                        $eligibilityTimestamp,
                    );

                    return $eligibility['eligible'];
                })
                ->values();

            $votedElectionIds = Vote::query()
                ->where('voter_id', $request->user()->id)
                ->whereIn('election_id', $elections->pluck('id'))
                ->pluck('election_id')
                ->mapWithKeys(static fn (mixed $id): array => [(int) $id => true]);

            $elections = $elections->map(function (array $election) use ($votedElectionIds): array {
                $election['hasVoted'] = $votedElectionIds->has((int) $election['id']);

                return $election;
            });
        } catch (IntegrationException) {
            return view('voting.error', [
                'message' => 'The election web service is temporarily unavailable.',
            ]);
        }

        return view('voting.index', ['elections' => $elections]);
    }

    public function results(
        Request $request,
        int $election,
        VotingIntegration $integration,
        VoterEligibilityAuthorizer $eligibilityAuthorizer,
        VoteTallyService $tallyService,
    ): Response|View {
        [$requestId, $timestamp] = $this->newEnvelope();

        try {
            $details = $integration->election($election, $requestId, $timestamp);

            if ($details['eligibility']['type'] === 'PRIVATE') {
                [$eligibilityRequestId, $eligibilityTimestamp] = $this->newEnvelope();
                $voterEligibility = $integration->voterEligibility(
                    (int) $request->user()->getKey(),
                    $election,
                    $eligibilityRequestId,
                    $eligibilityTimestamp,
                );

                try {
                    $eligibilityAuthorizer->assertEligible($voterEligibility, $details['eligibility']);
                } catch (VoteAccessDenied) {
                    abort(404);
                }
            }
        } catch (IntegrationException) {
            return response()->view('voting.error', [
                'message' => 'A required teammate web service is temporarily unavailable.',
            ], 503);
        }

        abort_unless(
            in_array($details['status'], ['VOTING_OPEN', 'CLOSED'], true),
            404,
        );

        $hasVoted = Vote::query()
            ->where('voter_id', $request->user()->id)
            ->where('election_id', $election)
            ->exists();

        $tally = $details['status'] === 'CLOSED'
            ? $tallyService->tally($election, $details['candidacies'])
            : ['tally' => [], 'totalVotes' => 0];

        return response()
            ->view('voting.results', [
                'election' => $details,
                'results' => $tally['tally'],
                'totalVotes' => $tally['totalVotes'],
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
