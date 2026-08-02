<?php

declare(strict_types=1);

namespace VoteTrust\Election\Tests\Security;

use VoteTrust\Election\Models\Election;

/**
 * Test-only model of the legacy flaw. It is never routed by the web application.
 */
final class InsecureElectionWorkflow
{
    public function forceStatus(Election $election, string $status): Election
    {
        $election->set('status', $status);
        $election->save();

        return $election;
    }
}

