<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Election;
use App\Support\ElectionCardStatus;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class ElectionCardStatusTest extends TestCase
{
    public function test_nomination_status_is_open_through_the_inclusive_end_time(): void
    {
        $status = ElectionCardStatus::forSchedule(
            Election::NOMINATION_OPEN,
            '2026-08-21 15:28:00',
            CarbonImmutable::parse('2026-08-21 15:28:00', 'Asia/Kuala_Lumpur'),
        );

        self::assertSame('Nomination Open', $status->label);
        self::assertSame('nomination-open', $status->modifier);
    }

    public function test_expired_nomination_status_is_presented_as_closed(): void
    {
        $status = ElectionCardStatus::forSchedule(
            Election::NOMINATION_OPEN,
            '2026-08-21 15:28:00',
            CarbonImmutable::parse('2026-08-21 15:28:01', 'Asia/Kuala_Lumpur'),
        );

        self::assertSame('Nominations Closed', $status->label);
        self::assertSame('nomination-closed', $status->modifier);
    }

    public function test_invalid_or_missing_end_dates_preserve_the_lifecycle_status(): void
    {
        foreach ([null, 'not-a-date'] as $end) {
            $status = ElectionCardStatus::forSchedule(
                Election::NOMINATION_OPEN,
                $end,
                CarbonImmutable::parse('2026-08-21 15:28:01', 'Asia/Kuala_Lumpur'),
            );

            self::assertSame('Nomination Open', $status->label);
            self::assertSame('nomination-open', $status->modifier);
        }
    }
}
