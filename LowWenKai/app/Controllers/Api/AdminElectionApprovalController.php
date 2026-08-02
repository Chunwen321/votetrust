<?php

declare(strict_types=1);

namespace VoteTrust\Election\Controllers\Api;

use VoteTrust\Election\Core\Config;
use VoteTrust\Election\Core\Request;
use VoteTrust\Election\Core\Response;
use VoteTrust\Election\Exceptions\HttpException;
use VoteTrust\Election\Exceptions\ValidationException;
use VoteTrust\Election\Models\User;
use VoteTrust\Election\Security\ApiRequestValidator;
use VoteTrust\Election\Services\ApiEnvelope;
use VoteTrust\Election\Services\ElectionService;

final class AdminElectionApprovalController
{
    public function decide(Request $request): Response
    {
        $payload = $request->json();
        $requestId = (string) ($payload['requestID'] ?? 'UNKNOWN');

        try {
            $expectedKey = (string) Config::get('ADMIN_API_KEY', 'local-admin-integration-key');
            $providedKey = (string) $request->header('X-Admin-API-Key', '');
            if ($providedKey === '' || !hash_equals($expectedKey, $providedKey)) {
                return Response::json(ApiEnvelope::failure($requestId, 'Invalid Admin Module API key.'), 401);
            }

            $envelope = (new ApiRequestValidator())->validateEnvelope($payload);
            $requestId = $envelope['requestID'];
            $decision = strtoupper(trim((string) ($payload['decision'] ?? '')));
            if (!in_array($decision, ['APPROVE', 'REJECT'], true)) {
                throw new ValidationException(['decision' => 'Decision must be APPROVE or REJECT.']);
            }
            $adminId = filter_var($payload['adminUserID'] ?? null, FILTER_VALIDATE_INT);
            if ($adminId === false || $adminId < 1) {
                throw new ValidationException(['adminUserID' => 'A valid administrator user ID is required.']);
            }
            $admin = User::find((int) $adminId);
            if (!$admin instanceof User || !$admin->isAdmin()) {
                return Response::json(ApiEnvelope::failure($requestId, 'Administrator account not found.'), 403);
            }

            $election = ElectionService::fromConfig()->transitionFromAdminApi(
                $admin,
                (int) $request->route('id'),
                strtolower($decision),
                ['reason' => $payload['reason'] ?? null],
                $requestId
            );

            return Response::json(ApiEnvelope::success($requestId, [
                'electionId' => $election->id(),
                'decision' => $decision,
                'lifecycleState' => $election->status(),
                'administratorUserID' => $admin->id(),
            ], 'Administrator decision recorded.'));
        } catch (ValidationException $exception) {
            return Response::json(ApiEnvelope::failure($requestId, $exception->getMessage(), $exception->errors), 422);
        } catch (HttpException $exception) {
            return Response::json(ApiEnvelope::failure($requestId, $exception->getMessage()), $exception->statusCode);
        }
    }
}
