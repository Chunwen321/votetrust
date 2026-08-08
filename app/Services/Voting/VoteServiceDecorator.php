<?php

namespace App\Services\Voting;

use App\Contracts\VoteCastingService;

// PIC: Goh Chun Wen - Base Decorator for composable vote security controls.
abstract class VoteServiceDecorator implements VoteCastingService
{
    public function __construct(protected readonly VoteCastingService $next) {}
}
