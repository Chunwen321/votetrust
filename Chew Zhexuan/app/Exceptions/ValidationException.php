<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Exceptions;

final class ValidationException extends HttpException
{
    /** @param array<string, string> $errors */
    public function __construct(string $message = 'Please correct the highlighted fields.', array $errors = [])
    {
        parent::__construct($message, 422, $errors);
    }
}
