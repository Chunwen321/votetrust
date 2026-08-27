<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Security\ApiRequestValidator;
use App\Services\ApiEnvelope;
use App\Services\ElectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AdminElectionApprovalController extends Controller
{
    public function __construct(
        private readonly ElectionService $service,
        private readonly ApiRequestValidator $validator,
    ) {}

    public function decide(Request $request, int $election): JsonResponse
    {
        $payload = $request->json()->all();
        $requestId = (string) ($payload['requestID'] ?? 'UNKNOWN');
        $expectedKey = (string) config('election.admin_api_key');
        $providedKey = (string) $request->header('X-Admin-API-Key', '');
        if ($expectedKey === '' || $providedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json(ApiEnvelope::failure($requestId, 'Invalid Admin Module API key.'), 401);
        }

        try {
            $envelope = $this->validator->validateEnvelope($payload);
            $requestId = $envelope['requestID'];
            $decision = strtoupper(trim((string) ($payload['decision'] ?? '')));
            if (! in_array($decision, ['VERIFY_PAYMENT', 'APPROVE', 'RETURN_FOR_PAYMENT', 'REJECT'], true)) {
                throw ValidationException::withMessages(['decision' => 'Decision must be VERIFY_PAYMENT, APPROVE, RETURN_FOR_PAYMENT, or REJECT.']);
            }
            $adminId = filter_var($payload['adminUserID'] ?? null, FILTER_VALIDATE_INT);
            if ($adminId === false || $adminId < 1) {
                throw ValidationException::withMessages(['adminUserID' => 'A valid administrator user ID is required.']);
            }
            $admin = User::query()->find((int) $adminId);
            if (! $admin instanceof User || ! $this->service->isAdmin($admin)) {
                return response()->json(ApiEnvelope::failure($requestId, 'Administrator account not found.'), 403);
            }

            $record = $this->service->transitionFromAdminApi(
                $admin,
                $election,
                strtolower($decision),
                [
                    'reason' => $payload['reason'] ?? null,
                    'version' => $payload['version'] ?? null,
                ],
                $requestId,
            );

            return response()->json(ApiEnvelope::success($requestId, [
                'electionId' => (int) $record->getKey(),
                'decision' => $decision,
                'lifecycleState' => (string) $record->status,
                'administratorUserID' => (int) $admin->getKey(),
            ], 'Administrator decision recorded.'));
        } catch (ValidationException $exception) {
            return response()->json(ApiEnvelope::failure($requestId, 'The API request is invalid.', $exception->errors()), 422);
        } catch (AuthorizationException $exception) {
            return response()->json(ApiEnvelope::failure($requestId, $exception->getMessage()), 403);
        } catch (ModelNotFoundException) {
            return response()->json(ApiEnvelope::failure($requestId, 'Election not found.'), 404);
        } catch (BusinessRuleException $exception) {
            return response()->json(ApiEnvelope::failure($requestId, $exception->getMessage()), $exception->statusCode);
        }
    }
}
