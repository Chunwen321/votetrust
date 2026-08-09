<?php

namespace App\Support;

use Symfony\Component\Intl\Countries;

// PIC: Low Wen Kai - ISO 3166-1 country choices for Election eligibility.
final class CountryList
{
    public const ANY = 'ANY';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [self::ANY => 'Any nationality', ...Countries::getNames('en')];
    }

    public static function isValidCode(?string $countryCode): bool
    {
        return is_string($countryCode)
            && preg_match('/^[A-Z]{2}$/', $countryCode) === 1
            && Countries::exists($countryCode);
    }

    public static function name(?string $countryCode): ?string
    {
        return self::isValidCode($countryCode) ? Countries::getName($countryCode, 'en') : null;
    }
}
