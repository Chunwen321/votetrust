<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Security\ApiRequestValidator;
use App\Services\ApiEnvelope;
use App\Services\ElectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ElectionAvailabilityController extends Controller
{
    public function __construct(
        private readonly ElectionService $service,
        private readonly ApiRequestValidator $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $requestId = (string) $request->input('requestID', 'UNKNOWN');
        try {
            $envelope = $this->validator->validateEnvelope($request->all());
            $requestId = $envelope['requestID'];
            $this->service->advanceScheduledElections();
            $elections = $this->service->availableForVoting();

            return response()->json(ApiEnvelope::success($requestId, [
                'count' => count($elections),
                'elections' => $elections,
            ], 'Available elections retrieved.'));
        } catch (ValidationException $exception) {
            return response()->json(ApiEnvelope::failure(
                $requestId,
                'The API request envelope is invalid.',
                $exception->errors(),
            ), 422);
        }
    }

    public function catalog(Request $request): JsonResponse
    {
        $requestId = (string) $request->input('requestID', 'UNKNOWN');
        try {
            $envelope = $this->validator->validateEnvelope($request->all());
            $requestId = $envelope['requestID'];
            $this->service->advanceScheduledElections();
            $elections = $this->service->voterElectionCatalog();

            return response()->json(ApiEnvelope::success($requestId, [
                'count' => count($elections),
                'elections' => $elections,
            ], 'Voter election catalog retrieved.'));
        } catch (ValidationException $exception) {
            return response()->json(ApiEnvelope::failure(
                $requestId,
                'The API request envelope is invalid.',
                $exception->errors(),
            ), 422);
        }
    }

    public function show(Request $request, int $election): JsonResponse
    {
        $requestId = (string) $request->input('requestID', 'UNKNOWN');
        try {
            $envelope = $this->validator->validateEnvelope($request->all());
            $requestId = $envelope['requestID'];
            $this->service->advanceScheduledElections();
            $record = Election::query()->find($election);
            if (! $record instanceof Election) {
                return response()->json(ApiEnvelope::failure($requestId, 'Election not found.'), 404);
            }

            return response()->json(ApiEnvelope::success(
                $requestId,
                $this->service->availability($record),
                'Election availability retrieved.',
            ));
        } catch (ValidationException $exception) {
            return response()->json(ApiEnvelope::failure(
                $requestId,
                'The API request envelope is invalid.',
                $exception->errors(),
            ), 422);
        }
    }
}
