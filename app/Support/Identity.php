<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Support;

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
}
