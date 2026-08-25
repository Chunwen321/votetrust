<?php

declare(strict_types=1);

namespace Demo\Scenario;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Module 2 — Error Handling & Logging: cryptographic module failures.
 *
 * The rule this class enforces: full internal detail (exception class,
 * message, context) is written to a log file for developers/ops, but never
 * handed to the caller. Callers only ever receive SanitizedCryptoException,
 * whose message is fixed and contains nothing about the underlying failure.
 */
final class CryptoFailureLogger
{
    public function __construct(private readonly string $logPath) {}

    public function log(Throwable $exception, string $context, string $severity = 'CRITICAL'): void
    {
        $dir = dirname($this->logPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Unable to create log directory: {$dir}");
        }

        $line = sprintf(
            '[%s] severity=%s context=%s exception=%s message=%s%s',
            (new DateTimeImmutable())->format(DATE_ATOM),
            $severity,
            $context,
            $exception::class,
            $exception->getMessage(),
            PHP_EOL,
        );

        file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
    }
}

/**
 * The only thing ever shown to an end user after a cryptographic failure.
 * Deliberately carries no exception class name, message, file path, or
 * stack trace from the underlying failure.
 */
final class SanitizedCryptoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A secure identifier could not be generated right now. Please try again.');
    }
}
