<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Exceptions;

final class ConcurrencyException extends BusinessRuleException
{
    public function __construct(string $message = 'This election was changed by another request. Reload and try again.')
    {
        parent::__construct($message);
    }
}
