<?php

declare(strict_types=1);

namespace VoteTrust\Election\Exceptions;

final class BusinessRuleException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 409);
    }
}

