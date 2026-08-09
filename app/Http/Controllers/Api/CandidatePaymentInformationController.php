<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidacy;
use App\Models\User;
use App\Services\CandidateRegistration\CandidateApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PIC: Wong Shun Bin
 * Provides minimal Candidate and Organisation information to the Payment module.
 */
final class CandidatePaymentInformationController extends Controller
{
    public function __invoke(Request $request, int $candidate): JsonResponse
    {
        $requestId = (string) $request->query('requestID');
        $electionId = (int) $request->integer('electionId');

        $candidacy = Candidacy::query()
            ->with('election.organizer')
            ->where('user_id', $candidate)
            ->where('election_id', $electionId)
            ->first();
        $candidateUser = User::query()->find($candidate);

        if (! $candidateUser instanceof User || ! $candidacy instanceof Candidacy) {
            return response()->json(CandidateApiEnvelope::failure(
                $requestId,
                'Candidate application not found.',
                status: 'F',
            ), 404);
        }

        $organizer = $candidacy->election?->organizer;

        if ($candidateUser === null || $organizer === null) {
            return response()->json(CandidateApiEnvelope::failure(
                $requestId,
                'Candidate or Organisation information is unavailable.',
                status: 'F',
            ), 409);
        }

        return response()->json(CandidateApiEnvelope::success($requestId, [
            'candidate' => [
                'candidateId' => (int) $candidateUser->getKey(),
                'name' => (string) $candidateUser->display_name,
                'email' => (string) $candidateUser->email,
            ],
            'organisation' => [
                'organisationId' => (int) $organizer->getKey(),
                'name' => (string) $organizer->display_name,
                'email' => (string) $organizer->email,
            ],
            'candidacy' => [
                'candidacyId' => (int) $candidacy->getKey(),
                'electionId' => (int) $candidacy->election_id,
                'status' => (string) $candidacy->status,
            ],
        ], 'Candidate and Organisation information retrieved.'));
    }
}
