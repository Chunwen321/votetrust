<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Exceptions;

final class AuthenticationException extends HttpException
{
    public function __construct(string $message = 'Authentication is required.')
    {
        parent::__construct($message, 401);
    }
}
