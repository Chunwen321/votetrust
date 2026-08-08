<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Contracts;

use Carbon\CarbonImmutable;

interface Clock
{
    public function now(): CarbonImmutable;
}
