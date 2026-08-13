<?php

namespace App\Http\Controllers\Api;

use App\Contracts\VotingIntegration;
use App\Http\Controllers\Controller;
use App\Models\Vote;
use App\Services\Voting\IntegrationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// PIC: Goh Chun Wen - Voting Process module REST provider.
class VotingApiController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'voterId' => ['required', 'integer', 'min:1'],
            'electionId' => ['required', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->failure($request, 'Invalid voting status request.', 422, $validator->errors()->toArray());
        }

        $data = $validator->validated();

        return $this->success($request, [
            'voterId' => (int) $data['voterId'],
            'electionId' => (int) $data['electionId'],
            'hasVoted' => Vote::query()
                ->where('voter_id', $data['voterId'])
                ->where('election_id', $data['electionId'])
                ->exists(),
        ], 'Voting status retrieved.');
    }

    public function tally(
        Request $request,
        int $election,
        VotingIntegration $integration,
    ): JsonResponse {
        try {
            $ballot = $integration->election(
                $election,
                (string) $request->query('requestID'),
                (string) $request->query('timestamp'),
            );
        } catch (IntegrationException) {
            return $this->failure(
                $request,
                'The Election Module is temporarily unavailable.',
                502,
                ifaStatus: 'E',
            );
        }

        if ($ballot['status'] !== 'CLOSED') {
            return $this->failure($request, 'Votes can only be tallied after the election closes.', 422);
        }

        $counts = Vote::query()
            ->where('election_id', $election)
            ->selectRaw('candidate_id, COUNT(*) as total')
            ->groupBy('candidate_id')
            ->pluck('total', 'candidate_id');

        $tally = collect($ballot['candidacies'])
            ->map(fn (array $candidate): array => [
                'candidateId' => $candidate['candidateId'],
                'candidateName' => $candidate['name'],
                'votes' => (int) ($counts[$candidate['candidateId']] ?? 0),
            ])
            ->values();

        return $this->success($request, [
            'electionId' => $election,
            'tally' => $tally,
        ], 'Vote tally retrieved.');
    }

    /** @param array<string, mixed> $data */
    private function success(Request $request, array $data, string $message): JsonResponse
    {
        return response()->json([
            'status' => 'S',
            'message' => $message,
            'requestID' => $request->query('requestID'),
            'timestamp' => now()->toAtomString(),
            'data' => $data,
        ]);
    }

    /** @param array<string, mixed> $errors */
    private function failure(
        Request $request,
        string $message,
        int $httpStatus,
        array $errors = [],
        string $ifaStatus = 'F',
    ): JsonResponse {
        return response()->json([
            'status' => $ifaStatus,
            'message' => $message,
            'requestID' => $request->query('requestID', 'UNKNOWN'),
            'timestamp' => now()->toAtomString(),
            'errors' => $errors,
        ], $httpStatus);
    }
}
