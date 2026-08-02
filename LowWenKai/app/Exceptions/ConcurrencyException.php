<?php

declare(strict_types=1);

namespace VoteTrust\Election\Exceptions;

final class ConcurrencyException extends HttpException
{
    public function __construct(string $message = 'This election was changed by another request. Reload and try again.')
    {
        parent::__construct($message, 409);
    }
}

