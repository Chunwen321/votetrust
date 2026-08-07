<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Exceptions;

class HttpException extends \RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(string $message, public readonly int $statusCode = 400, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
