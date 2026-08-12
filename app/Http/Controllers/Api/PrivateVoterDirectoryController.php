<?php

// PIC: Chew Zhexuan — User/Voter module provider for the private-voter directory.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrivateVoterDirectoryRequest;
use App\Services\VoterService;
use Illuminate\Http\JsonResponse;

final class PrivateVoterDirectoryController extends Controller
{
    public function __construct(private readonly VoterService $voters) {}

    public function __invoke(PrivateVoterDirectoryRequest $request): JsonResponse
    {
        $result = $this->voters->privateVoterDirectory(
            (int) $request->validated('electionId'),
            (string) $request->validated('search', ''),
            (int) $request->validated('page', 1),
            (int) $request->validated('perPage', 25),
        );

        return response()->json([
            'status' => 'S',
            'message' => 'Private voter directory loaded.',
            'requestID' => (string) $request->validated('requestID'),
            'timestamp' => now()->toAtomString(),
            'data' => [
                'electionId' => (int) $request->validated('electionId'),
                ...$result,
            ],
        ]);
    }
}
