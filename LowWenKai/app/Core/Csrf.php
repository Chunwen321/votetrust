<?php

declare(strict_types=1);

namespace VoteTrust\Election\Core;

use VoteTrust\Election\Exceptions\AuthorizationException;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf_token', $token);
        }

        return $token;
    }

    public static function assertValid(mixed $provided): void
    {
        $stored = Session::get('_csrf_token');
        if (!is_string($stored) || !is_string($provided) || !hash_equals($stored, $provided)) {
            throw new AuthorizationException('The form session expired. Refresh the page and try again.');
        }
    }
}

