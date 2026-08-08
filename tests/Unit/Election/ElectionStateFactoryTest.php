<?php

// PIC: Low Wen Kai - Election Management Module

namespace Tests\Unit\Election;

use App\States\ElectionStateFactory;
use App\States\ElectionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ElectionStateFactoryTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function statuses(): iterable
    {
        foreach (ElectionStatus::cases() as $status) {
            yield $status->name => [$status->value];
        }
    }

    #[DataProvider('statuses')]
    public function test_factory_builds_each_lifecycle_state(string $status): void
    {
        self::assertSame($status, (new ElectionStateFactory)->make($status)->name());
    }
}
