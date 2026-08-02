<?php

declare(strict_types=1);

namespace VoteTrust\Election\Security;

use VoteTrust\Election\Core\Config;

final class SecurityAuditLogger
{
    public function log(string $event, array $context = []): void
    {
        $path = (string) Config::get('SECURITY_LOG_PATH', dirname(__DIR__, 2) . '/storage/logs/security.log');
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        $record = [
            'timestamp' => date(DATE_ATOM),
            'event' => $event,
            'context' => $context,
        ];

        file_put_contents(
            $path,
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

