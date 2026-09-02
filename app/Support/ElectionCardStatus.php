<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Election;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * A schedule-aware lifecycle label for participant-facing election cards.
 *
 * The presentation remains schedule-aware if a scheduler run is briefly late.
 */
final readonly class ElectionCardStatus
{
    public function __construct(
        public string $label,
        public string $modifier,
    ) {}

    public static function forSchedule(
        string $status,
        mixed $nominationEnd,
        ?CarbonImmutable $now = null,
    ): self {
        $now ??= CarbonImmutable::now((string) config('app.timezone', 'UTC'));
        $nominationEnd = self::date($nominationEnd);

        if ($status === Election::NOMINATION_OPEN
            && $nominationEnd !== null
            && $now->gt($nominationEnd)) {
            return new self('Nominations Closed', 'nomination-closed');
        }

        return new self(
            str($status)->replace('_', ' ')->lower()->headline()->toString(),
            strtolower(str_replace('_', '-', $status)),
        );
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse((string) $value);

            return $date->setTimezone((string) config('app.timezone', 'UTC'));
        } catch (Throwable) {
            return null;
        }
    }
}
