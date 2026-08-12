<?php

// PIC: Chew Zhexuan — User/Voter module provider for organiser-imported private voters.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrivateVoterEnrollmentRequest;
use App\Services\VoterService;
use Illuminate\Http\JsonResponse;

final class PrivateVoterEnrollmentController extends Controller
{
    public function __construct(private readonly VoterService $voters) {}

    public function __invoke(PrivateVoterEnrollmentRequest $request): JsonResponse
    {
        $result = $this->voters->importPrivateRoster(
            (int) $request->validated('electionId'),
            (int) $request->validated('organizerUserId'),
            $request->validated('voters'),
        );

        return response()->json([
            'status' => 'S',
            'message' => 'Private voter roster processed.',
            'requestID' => (string) $request->validated('requestID'),
            'timestamp' => now()->toAtomString(),
            'data' => [
                'electionId' => (int) $request->validated('electionId'),
                ...$result,
            ],
        ]);
    }
}
