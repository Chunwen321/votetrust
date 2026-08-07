<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Controllers\Api;

use VoteTrust\UserAdmin\Core\Request;
use VoteTrust\UserAdmin\Core\Response;
use VoteTrust\UserAdmin\Exceptions\ValidationException;
use VoteTrust\UserAdmin\Services\ApiRequestValidator;
use VoteTrust\UserAdmin\Services\VoterService;

final class VoterEligibilityController
{
    public function show(Request $request): Response
    {
        (new ApiRequestValidator())->assertInternalRequest($request);
        $voterId = filter_var($request->input('voterId'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $electionId = filter_var($request->input('electionId'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($voterId === false || $electionId === false) {
            throw new ValidationException('Invalid eligibility request.', [
                'voterId' => 'voterId must be a positive integer.',
                'electionId' => 'electionId must be a positive integer.',
            ]);
        }

        $eligibility = (new VoterService())->eligibility((int) $voterId, (int) $electionId);

        return Response::json([
            'status' => 'S',
            'message' => 'Voter eligibility retrieved.',
            'requestID' => (string) $request->input('requestID'),
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'data' => [
                'voterId' => (int) $voterId,
                'electionId' => (int) $electionId,
                ...$eligibility,
            ],
        ]);
    }
}
