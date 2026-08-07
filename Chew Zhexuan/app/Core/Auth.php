<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Core;

use VoteTrust\UserAdmin\Exceptions\AuthenticationException;
use VoteTrust\UserAdmin\Models\UserRepository;
use VoteTrust\UserAdmin\Security\Authentication\LoginProtectionStrategyFactory;
use VoteTrust\UserAdmin\Security\Session\SessionTerminationStrategyFactory;

final class Auth
{
    /** @var array<string, mixed>|null */
    private static ?array $resolvedUser = null;

    public static function attempt(string $username, string $password): bool
    {
        $user = (new UserRepository())->findByUsername(trim($username));
        if ($user === null) {
            password_verify($password, '$2y$10$abcdefghijklmnopqrstuv12345678901234567890123456789012');

            return false;
        }

        // Verify before checking the account state so disabled accounts do not
        // produce a faster response than unknown usernames.
        $passwordMatches = password_verify($password, (string) $user['password_hash']);
        $protection = LoginProtectionStrategyFactory::make();
        if (!$protection->allows($user)) {
            return false;
        }
        if (!$passwordMatches) {
            $protection->recordFailure($user);

            return false;
        }

        $protection->recordSuccess($user);

        Session::regenerate();
        Session::put('authenticated_user_id', (int) $user['id']);
        self::$resolvedUser = $user;

        return true;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolvedUser !== null) {
            return self::$resolvedUser;
        }

        $id = Session::get('authenticated_user_id');
        if (!is_numeric($id)) {
            return null;
        }

        self::$resolvedUser = (new UserRepository())->findById((int) $id);
        if (self::$resolvedUser !== null && (self::$resolvedUser['account_status'] ?? 'ACTIVE') !== 'ACTIVE') {
            self::logout();

            return null;
        }

        return self::$resolvedUser;
    }

    /** @return array<string, mixed> */
    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            throw new AuthenticationException('Please sign in to continue.');
        }

        return $user;
    }

    /** @param array<string, mixed> $user */
    public static function isAdmin(array $user): bool
    {
        return (int) ($user['is_admin'] ?? 0) === 1;
    }

    /** @param array<string, mixed> $user */
    public static function isVoter(array $user): bool
    {
        return (int) ($user['is_voter'] ?? 0) === 1;
    }

    public static function logout(): void
    {
        self::$resolvedUser = null;
        SessionTerminationStrategyFactory::make()->terminate();
    }
}
