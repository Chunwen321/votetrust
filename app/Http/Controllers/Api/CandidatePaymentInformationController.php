<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidacy;
use App\Models\Candidate;
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

        $candidateRecord = Candidate::query()
            ->with('user')
            ->find($candidate);
        $candidacy = Candidacy::query()
            ->with('election.organizer')
            ->where('candidate_id', $candidate)
            ->where('election_id', $electionId)
            ->first();

        if (! $candidateRecord instanceof Candidate || ! $candidacy instanceof Candidacy) {
            return response()->json(CandidateApiEnvelope::failure(
                $requestId,
                'Candidate application not found.',
                status: 'F',
            ), 404);
        }

        $candidateUser = $candidateRecord->user;
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
                'candidateId' => (int) $candidateRecord->getKey(),
                'name' => (string) ($candidateRecord->full_name ?: $candidateUser->display_name),
                'email' => (string) ($candidateRecord->email ?: $candidateUser->email),
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
