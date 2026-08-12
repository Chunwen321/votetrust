<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

final class Identity
{
    public static function normaliseIcNumber(string $icNumber): string
    {
        return preg_replace('/[\s-]+/', '', trim($icNumber)) ?? '';
    }

    public static function isValidIcNumber(string $icNumber): bool
    {
        return preg_match('/^\d{12}$/', self::normaliseIcNumber($icNumber)) === 1;
    }

    public static function normalisePassportNumber(string $passportNumber): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', trim($passportNumber)) ?? '');
    }

    public static function isValidPassportNumber(string $passportNumber): bool
    {
        return preg_match('/^[A-Z0-9]{6,20}$/', self::normalisePassportNumber($passportNumber)) === 1;
    }

    public static function dateOfBirthFromMalaysianIc(string $icNumber): ?CarbonImmutable
    {
        $normalised = self::normaliseIcNumber($icNumber);
        if (! self::isValidIcNumber($normalised)) {
            return null;
        }

        $shortYear = (int) substr($normalised, 0, 2);
        $monthAndDay = substr($normalised, 2, 4);
        $currentYear = CarbonImmutable::now()->year;
        $year = 2000 + $shortYear;
        if ($year > $currentYear) {
            $year -= 100;
        }

        $value = sprintf('%04d%s', $year, $monthAndDay);
        $date = CarbonImmutable::createFromFormat('!Ymd', $value);

        return $date !== false && $date->format('Ymd') === $value && ! $date->isFuture()
            ? $date
            : null;
    }
}
