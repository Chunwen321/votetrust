<?php

// PIC: Wong Shun Bin — Candidate Module participant API consumed by User administration.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidacy;
use App\Services\CandidateRegistration\CandidateApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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

    public function store(Request $request, int $election): JsonResponse
    {
        $requestId = (string) $request->input('requestID');
        $validator = Validator::make($request->json()->all(), [
            'electionId' => ['required', 'integer', 'min:1'],
            'userId' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:Pending,Approved,Rejected'],
        ]);
        $validator->after(function ($validator) use ($election): void {
            if ((int) ($validator->getData()['electionId'] ?? 0) !== $election) {
                $validator->errors()->add('electionId', 'The requested election does not match the route.');
            }
        });
        $validated = $validator->validate();

        $candidacy = Candidacy::query()->firstOrNew([
            'election_id' => $election,
            'user_id' => (int) $validated['userId'],
        ]);
        $candidacy->status = (string) $validated['status'];
        if (! $candidacy->exists) {
            $candidacy->registered_at = now();
        }
        $candidacy->save();

        return response()->json(CandidateApiEnvelope::success($requestId, [
            'electionId' => $election,
            'userId' => (int) $candidacy->user_id,
            'status' => (string) $candidacy->status,
        ], 'Candidate participant saved.'));
    }

    public function update(Request $request, int $election, int $user): JsonResponse
    {
        $requestId = (string) $request->input('requestID');
        $validator = Validator::make($request->json()->all(), [
            'electionId' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:Pending,Approved,Rejected'],
        ]);
        $validator->after(function ($validator) use ($election): void {
            if ((int) ($validator->getData()['electionId'] ?? 0) !== $election) {
                $validator->errors()->add('electionId', 'The requested election does not match the route.');
            }
        });
        $validated = $validator->validate();
        $candidacy = Candidacy::query()->where('election_id', $election)->where('user_id', $user)->first();
        if (! $candidacy instanceof Candidacy) {
            return response()->json(CandidateApiEnvelope::failure(
                $requestId,
                'Candidate participant not found.',
                ['userId' => ['No candidate participant was found for this election.']],
            ), 404);
        }
        $candidacy->status = (string) $validated['status'];
        $candidacy->save();

        return response()->json(CandidateApiEnvelope::success($requestId, [
            'electionId' => $election,
            'userId' => $user,
            'status' => (string) $candidacy->status,
        ], 'Candidate participant updated.'));
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
