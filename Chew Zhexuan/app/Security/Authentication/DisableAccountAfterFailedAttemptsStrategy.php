<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Security\Authentication;

use VoteTrust\UserAdmin\Core\Config;
use VoteTrust\UserAdmin\Models\UserRepository;

/**
 * Permanently disables an active account once it reaches the configured number
 * of consecutive failed sign-in attempts. An administrator must reactivate it.
 */
final class DisableAccountAfterFailedAttemptsStrategy implements LoginProtectionStrategy
{
    private const DEFAULT_MAX_FAILED_ATTEMPTS = 5;

    private readonly int $maximumFailedAttempts;

    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
        $configuredMaximum = filter_var(
            Config::get('AUTH_MAX_FAILED_ATTEMPTS', self::DEFAULT_MAX_FAILED_ATTEMPTS),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]]
        );
        $this->maximumFailedAttempts = is_int($configuredMaximum)
            ? $configuredMaximum
            : self::DEFAULT_MAX_FAILED_ATTEMPTS;
    }

    public function allows(array $user): bool
    {
        return ($user['account_status'] ?? 'ACTIVE') === 'ACTIVE';
    }

    public function recordFailure(array $user): void
    {
        $this->users->incrementFailedLoginAttempts((int) $user['id'], $this->maximumFailedAttempts);
    }

    public function recordSuccess(array $user): void
    {
        $this->users->clearFailedLoginAttempts((int) $user['id']);
    }
}
