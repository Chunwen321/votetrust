<?php

declare(strict_types=1);

namespace Tests\Support;

use LogicException;

final class TestDatabaseGuard
{
    public static function assertProcessEnvironmentIsSafe(): void
    {
        self::assertSafe([
            'APP_ENV' => self::environmentValue('APP_ENV'),
            'DB_CONNECTION' => self::environmentValue('DB_CONNECTION'),
            'DB_DATABASE' => self::environmentValue('DB_DATABASE'),
            'DB_URL' => self::environmentValue('DB_URL'),
        ]);
    }

    /** @param array{APP_ENV:?string,DB_CONNECTION:?string,DB_DATABASE:?string,DB_URL:?string} $environment */
    public static function assertSafe(array $environment): void
    {
        $safe = $environment['APP_ENV'] === 'testing'
            && $environment['DB_CONNECTION'] === 'sqlite'
            && $environment['DB_DATABASE'] === ':memory:'
            && ($environment['DB_URL'] === null || $environment['DB_URL'] === '');

        if (! $safe) {
            throw new LogicException(
                'Unsafe PHPUnit database configuration blocked: tests require APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, and an empty DB_URL.'
            );
        }
    }

    private static function environmentValue(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return $value === false || $value === null ? null : (string) $value;
    }
}
