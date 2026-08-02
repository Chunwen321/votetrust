<?php

declare(strict_types=1);

namespace VoteTrust\Election\Core;

use DateTimeImmutable;

final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $currentTime)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->currentTime;
    }

    public function set(DateTimeImmutable $currentTime): void
    {
        $this->currentTime = $currentTime;
    }
}

