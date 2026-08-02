<?php

declare(strict_types=1);

namespace VoteTrust\Election\Core;

use VoteTrust\Election\Exceptions\AuthenticationException;
use VoteTrust\Election\Models\User;

final class Auth
{
    private static ?User $resolvedUser = null;

    public static function attempt(string $username, string $password): bool
    {
        $candidate = User::where('username', trim($username))->first();
        if (!$candidate instanceof User || !password_verify($password, $candidate->passwordHash())) {
            password_verify($password, '$2y$10$abcdefghijklmnopqrstuv12345678901234567890123456789012');

            return false;
        }

        Session::regenerate();
        Session::put('authenticated_user_id', $candidate->id());
        self::$resolvedUser = $candidate;

        return true;
    }

    public static function user(): ?User
    {
        if (self::$resolvedUser instanceof User) {
            return self::$resolvedUser;
        }

        $id = Session::get('authenticated_user_id');
        if (!is_numeric($id)) {
            return null;
        }

        self::$resolvedUser = User::find((int) $id);

        return self::$resolvedUser;
    }

    public static function requireUser(): User
    {
        $user = self::user();
        if (!$user instanceof User) {
            throw new AuthenticationException();
        }

        return $user;
    }

    public static function logout(): void
    {
        self::$resolvedUser = null;
        Session::destroy();
    }
}
