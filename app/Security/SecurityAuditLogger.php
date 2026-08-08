<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Security;

use Illuminate\Support\Facades\Log;

final class SecurityAuditLogger
{
    /** @param array<string, mixed> $context */
    public function log(string $event, array $context = []): void
    {
        Log::channel((string) config('election.security_log_channel', 'stack'))
            ->info($event, $context);
    }
}
