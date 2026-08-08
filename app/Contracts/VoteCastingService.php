<?php

namespace App\Contracts;

use App\Models\Vote;
use App\Services\Voting\VoteCommand;

// PIC: Goh Chun Wen - Decorator component contract for the Voting Process module.
interface VoteCastingService
{
    public function cast(VoteCommand $command): Vote;
}
