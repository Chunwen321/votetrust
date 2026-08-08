<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Services\Clock;

use App\Contracts\Clock;
use Carbon\CarbonImmutable;

final class SystemClock implements Clock
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }
}
