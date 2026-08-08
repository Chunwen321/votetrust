<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EligibilityRequest;
use App\Services\VoterService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

final class VoterEligibilityController extends Controller
{
    public function __construct(private readonly VoterService $voters) {}

    public function __invoke(EligibilityRequest $request): JsonResponse
    {
        $voterId = (int) $request->validated('voterId');
        $electionId = (int) $request->validated('electionId');

        try {
            $eligibility = $this->voters->eligibility($voterId, $electionId);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'E',
                'message' => 'The election does not exist.',
                'requestID' => (string) $request->validated('requestID'),
                'timestamp' => now()->toAtomString(),
                'errors' => [],
            ], 404);
        }

        return response()->json([
            'status' => 'S',
            'message' => 'Voter eligibility retrieved.',
            'requestID' => (string) $request->validated('requestID'),
            'timestamp' => now()->toAtomString(),
            'data' => [
                'voterId' => $voterId,
                'electionId' => $electionId,
                ...$eligibility,
            ],
        ]);
    }
}
