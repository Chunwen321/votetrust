<?php

declare(strict_types=1);

namespace VoteTrust\Election\Core;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}

