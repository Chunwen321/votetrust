<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Security\Authentication;

/**
 * Defines how the application responds to authentication attempts.
 *
 * Additional policies (for example, timed lockouts or IP throttling) can be
 * introduced without changing the authentication controller or Auth facade.
 */
interface LoginProtectionStrategy
{
    /** @param array<string, mixed> $user */
    public function allows(array $user): bool;

    /** @param array<string, mixed> $user */
    public function recordFailure(array $user): void;

    /** @param array<string, mixed> $user */
    public function recordSuccess(array $user): void;
}
