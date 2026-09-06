<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Console\Commands;

use App\Services\ElectionService;
use Illuminate\Console\Command;

final class AdvanceElectionSchedule extends Command
{
    protected $signature = 'elections:advance-schedule';

    protected $description = 'Advance nomination and voting states whose configured times have arrived';

    public function handle(ElectionService $elections): int
    {
        $summary = $elections->advanceScheduledElections();

        $this->info(sprintf(
            'Expired stale scheduled elections: %d; closed nominations: %d; opened voting: %d; closed elections: %d; voting blocked: %d.',
            $summary['electionsExpired'],
            $summary['nominationsClosed'],
            $summary['votingOpened'],
            $summary['electionsClosed'],
            $summary['votingBlocked'],
        ));

        return self::SUCCESS;
    }
}
