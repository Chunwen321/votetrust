<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Support;

final class Identity
{
    public static function normaliseName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    public static function normaliseIcNumber(string $icNumber): string
    {
        return preg_replace('/[\s-]+/', '', trim($icNumber)) ?? '';
    }

    public static function isValidIcNumber(string $icNumber): bool
    {
        return preg_match('/^\d{12}$/', self::normaliseIcNumber($icNumber)) === 1;
    }
}
