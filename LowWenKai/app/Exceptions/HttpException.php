<?php

declare(strict_types=1);

namespace VoteTrust\Election\Exceptions;

class HttpException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 500)
    {
        parent::__construct($message);
    }
}

