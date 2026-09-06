<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabaseGuard;

final class TestDatabaseGuardTest extends TestCase
{
    public function test_isolated_sqlite_memory_configuration_is_accepted(): void
    {
        TestDatabaseGuard::assertSafe($this->safeEnvironment());

        self::assertTrue(true);
    }

    /** @param array{APP_ENV:?string,DB_CONNECTION:?string,DB_DATABASE:?string,DB_URL:?string} $environment */
    #[DataProvider('unsafeEnvironmentProvider')]
    public function test_unsafe_database_configuration_is_rejected(array $environment): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsafe PHPUnit database configuration blocked');

        TestDatabaseGuard::assertSafe($environment);
    }

    /** @return iterable<string,array{array{APP_ENV:?string,DB_CONNECTION:?string,DB_DATABASE:?string,DB_URL:?string}}> */
    public static function unsafeEnvironmentProvider(): iterable
    {
        $safe = self::safeEnvironment();

        yield 'non-testing environment' => [array_replace($safe, ['APP_ENV' => 'local'])];
        yield 'mysql connection' => [array_replace($safe, ['DB_CONNECTION' => 'mysql'])];
        yield 'persistent sqlite file' => [array_replace($safe, ['DB_DATABASE' => 'database/testing.sqlite'])];
        yield 'database URL override' => [array_replace($safe, ['DB_URL' => 'mysql://example.invalid/test'])];
    }

    /** @return array{APP_ENV:string,DB_CONNECTION:string,DB_DATABASE:string,DB_URL:string} */
    private static function safeEnvironment(): array
    {
        return [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
        ];
    }
}
