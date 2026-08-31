<?php

// PIC: Wong Shun Bin — Candidate Module participant API consumed by User administration.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidacy;
use App\Services\CandidateRegistration\CandidateApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CandidateParticipantController extends Controller
{
    public function __invoke(int $election): JsonResponse
    {
        $requestId = (string) request()->query('requestID');

        return response()->json(CandidateApiEnvelope::success($requestId, [
            'electionId' => $election,
            'candidates' => Candidacy::query()
                ->where('election_id', $election)
                ->orderBy('registered_at')
                ->get()
                ->map(static fn (Candidacy $candidacy): array => [
                    'userId' => (int) $candidacy->user_id,
                    'status' => (string) $candidacy->status,
                    'registeredAt' => $candidacy->registered_at?->toAtomString(),
                ])
                ->values()
                ->all(),
        ], 'Candidate participants retrieved.'));
    }

    public function metrics(Request $request): JsonResponse
    {
        $requestId = (string) $request->query('requestID');

        return response()->json(CandidateApiEnvelope::success($requestId, [
            'candidates' => Candidacy::query()->distinct()->count('user_id'),
            'pending' => Candidacy::query()->whereRaw('LOWER(status) = ?', ['pending'])->count(),
        ], 'Candidate metrics retrieved.'));
    }
}
