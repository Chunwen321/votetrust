<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Exceptions;

final class AuthorizationException extends HttpException
{
    public function __construct(string $message = 'You are not authorised to perform this action.')
    {
        parent::__construct($message, 403);
    }
}
