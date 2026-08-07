<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Services;

use DateTimeImmutable;
use Throwable;
use VoteTrust\UserAdmin\Core\Config;
use VoteTrust\UserAdmin\Core\Request;
use VoteTrust\UserAdmin\Exceptions\AuthorizationException;
use VoteTrust\UserAdmin\Exceptions\ValidationException;

final class ApiRequestValidator
{
    public function assertInternalRequest(Request $request): void
    {
        // X-User-Api-Key is the preferred contract. X-Internal-Api-Key keeps
        // this module compatible with the existing Voting service adapter.
        $providedKey = (string) $request->header('X-User-Api-Key', '');
        if ($providedKey === '') {
            $providedKey = (string) $request->header('X-Internal-Api-Key', '');
        }
        $configuredKey = (string) Config::get('USER_API_KEY', '');
        if ($configuredKey === '' || !hash_equals($configuredKey, $providedKey)) {
            throw new AuthorizationException('The User Module API key is invalid.');
        }

        $requestId = (string) $request->input('requestID', '');
        $timestamp = (string) $request->input('timestamp', '');
        $errors = [];
        if (preg_match('/^[A-Za-z0-9_-]{8,64}$/', $requestId) !== 1) {
            $errors['requestID'] = 'requestID must contain 8–64 letters, numbers, underscores, or hyphens.';
        }
        if (!$this->isFreshTimestamp($timestamp)) {
            $errors['timestamp'] = 'timestamp must be ISO 8601 and within the permitted request window.';
        }
        if ($errors !== []) {
            throw new ValidationException('Invalid API request metadata.', $errors);
        }
    }

    private function isFreshTimestamp(string $timestamp): bool
    {
        try {
            $requestedAt = new DateTimeImmutable($timestamp);
        } catch (Throwable) {
            return false;
        }

        $maxAge = (int) Config::get('API_REQUEST_MAX_AGE_SECONDS', 300);

        return abs((new DateTimeImmutable())->getTimestamp() - $requestedAt->getTimestamp()) <= $maxAge;
    }
}
