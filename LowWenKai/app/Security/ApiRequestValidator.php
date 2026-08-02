<?php

declare(strict_types=1);

namespace VoteTrust\Election\Security;

use DateTimeImmutable;
use VoteTrust\Election\Exceptions\ValidationException;

final class ApiRequestValidator
{
    /** @param array<string, mixed> $input
     *  @return array{requestID:string,timestamp:string}
     */
    public function validateEnvelope(array $input): array
    {
        $requestId = trim((string) ($input['requestID'] ?? ''));
        $timestamp = trim((string) ($input['timestamp'] ?? ''));
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $requestId)) {
            $errors['requestID'] = 'requestID must contain 8-64 letters, numbers, underscores, or hyphens.';
        }

        try {
            $requestedAt = new DateTimeImmutable($timestamp);
            $difference = abs(time() - $requestedAt->getTimestamp());
            if ($difference > 300) {
                $errors['timestamp'] = 'timestamp must be within five minutes of server time.';
            }
        } catch (\Throwable) {
            $errors['timestamp'] = 'timestamp must be a valid ISO 8601 date-time.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, 'The API request envelope is invalid.');
        }

        return ['requestID' => $requestId, 'timestamp' => $timestamp];
    }
}

