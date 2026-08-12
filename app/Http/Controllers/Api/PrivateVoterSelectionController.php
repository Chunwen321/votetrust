<?php

// PIC: Chew Zhexuan — User/Voter module provider for real-time private-voter selection.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrivateVoterSelectionRequest;
use App\Services\VoterService;
use Illuminate\Http\JsonResponse;

final class PrivateVoterSelectionController extends Controller
{
    public function __construct(private readonly VoterService $voters) {}

    public function __invoke(PrivateVoterSelectionRequest $request): JsonResponse
    {
        $result = $this->voters->setPrivateVoterSelection(
            (int) $request->validated('electionId'),
            (int) $request->validated('organizerUserId'),
            (int) $request->validated('userId'),
            (bool) $request->validated('assigned'),
        );

        return response()->json([
            'status' => 'S',
            'message' => $result['assigned'] ? 'Private voter selection saved.' : 'Private voter selection removed.',
            'requestID' => (string) $request->validated('requestID'),
            'timestamp' => now()->toAtomString(),
            'data' => [
                'electionId' => (int) $request->validated('electionId'),
                ...$result,
            ],
        ]);
    }
}
