<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class BusinessRuleException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 409,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
