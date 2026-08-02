<?php

declare(strict_types=1);

namespace VoteTrust\Election\Exceptions;

final class ValidationException extends HttpException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors, string $message = 'Please correct the highlighted fields.')
    {
        parent::__construct($message, 422);
    }
}

