<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Identity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    public function test_it_normalises_a_malaysian_ic_number(): void
    {
        self::assertSame('010203101234', Identity::normaliseIcNumber('010203-10-1234'));
        self::assertSame('010203101234', Identity::normaliseIcNumber(' 010203 10 1234 '));
    }

    #[DataProvider('icNumbers')]
    public function test_it_validates_exactly_twelve_digits(string $value, bool $expected): void
    {
        self::assertSame($expected, Identity::isValidIcNumber($value));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function icNumbers(): iterable
    {
        yield 'plain' => ['010203101234', true];
        yield 'formatted' => ['010203-10-1234', true];
        yield 'short' => ['01020310123', false];
        yield 'letters' => ['01020310ABCD', false];
    }
}
