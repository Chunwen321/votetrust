<?php

declare(strict_types=1);

namespace VoteTrust\Election\Controllers\Api;

use VoteTrust\Election\Core\Request;
use VoteTrust\Election\Core\Response;
use VoteTrust\Election\Exceptions\HttpException;
use VoteTrust\Election\Exceptions\ValidationException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Security\ApiRequestValidator;
use VoteTrust\Election\Services\ApiEnvelope;
use VoteTrust\Election\Services\ElectionService;

final class ElectionAvailabilityController
{
    public function show(Request $request): Response
    {
        $input = $request->all();
        $requestId = (string) ($input['requestID'] ?? 'UNKNOWN');

        try {
            $envelope = (new ApiRequestValidator())->validateEnvelope($input);
            $requestId = $envelope['requestID'];
            $election = Election::find((int) $request->route('id'));
            if (!$election instanceof Election) {
                return Response::json(ApiEnvelope::failure($requestId, 'Election not found.'), 404);
            }

            $availability = ElectionService::fromConfig()->availability($election);

            return Response::json(ApiEnvelope::success(
                $requestId,
                $availability,
                'Election availability retrieved.'
            ));
        } catch (ValidationException $exception) {
            return Response::json(ApiEnvelope::failure($requestId, $exception->getMessage(), $exception->errors), 422);
        } catch (HttpException $exception) {
            return Response::json(ApiEnvelope::failure($requestId, $exception->getMessage()), $exception->statusCode);
        }
    }
}

