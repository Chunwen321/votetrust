<?php

// PIC: Low Wen Kai - Election Management Module

namespace Tests\Unit\Election;

use App\ValueObjects\EligibilityRules;
use PHPUnit\Framework\TestCase;

final class EligibilityRulesTest extends TestCase
{
    public function test_public_election_has_no_age_or_nationality_restrictions(): void
    {
        $rules = EligibilityRules::fromStored(EligibilityRules::publicElection()->toStorage());

        self::assertSame(EligibilityRules::PUBLIC, $rules->mode());
        self::assertNull($rules->minimumAge());
        self::assertFalse($rules->toArray()['nationality']['restricted']);
        self::assertStringNotContainsString('occupation', strtolower($rules->toStorage()));
    }

    public function test_restricted_election_can_store_minimum_age_only(): void
    {
        $rules = EligibilityRules::fromStored(EligibilityRules::restricted(18, 'ANY')->toStorage());

        self::assertSame(18, $rules->minimumAge());
        self::assertFalse($rules->toArray()['nationality']['restricted']);
    }

    public function test_restricted_election_can_store_nationality_only(): void
    {
        $rules = EligibilityRules::fromStored(EligibilityRules::restricted(null, 'MY')->toStorage());

        self::assertNull($rules->minimumAge());
        self::assertSame([
            'restricted' => true,
            'countryCode' => 'MY',
            'countryName' => 'Malaysia',
        ], $rules->toArray()['nationality']);
    }

    public function test_restricted_election_can_store_age_and_nationality(): void
    {
        $rules = EligibilityRules::fromStored(EligibilityRules::restricted(21, 'SG')->toStorage());

        self::assertSame(21, $rules->minimumAge());
        self::assertSame('SG', $rules->nationalityCountryCode());
        self::assertSame('Singapore', $rules->toArray()['nationality']['countryName']);
    }

    public function test_iso_country_options_are_available(): void
    {
        $options = EligibilityRules::nationalityOptions();

        self::assertSame('Any nationality', $options['ANY']);
        self::assertSame('Malaysia', $options['MY']);
        self::assertSame('Singapore', $options['SG']);
        self::assertArrayNotHasKey('MALAYSIAN', $options);
        self::assertArrayNotHasKey('NON_MALAYSIAN', $options);
        self::assertGreaterThan(200, count($options));
    }

    public function test_legacy_values_remain_readable_and_occupation_is_ignored(): void
    {
        $legacyJson = json_encode([
            'mode' => 'RESTRICTED',
            'minimumAge' => 18,
            'occupation' => 'STUDENT',
            'nationality' => 'MALAYSIAN',
        ], JSON_THROW_ON_ERROR);
        $rules = EligibilityRules::fromStored($legacyJson);

        self::assertSame(18, $rules->minimumAge());
        self::assertSame('MY', $rules->nationalityCountryCode());
        self::assertArrayNotHasKey('occupation', $rules->toArray());
        self::assertStringNotContainsString('Occupation', $rules->summary());
        self::assertSame('Verified members only', EligibilityRules::fromStored('Verified members only')->summary());

        $legacyNonMalaysian = EligibilityRules::fromStored(json_encode([
            'mode' => 'RESTRICTED',
            'nationality' => 'NON_MALAYSIAN',
        ], JSON_THROW_ON_ERROR))->toArray();
        self::assertTrue($legacyNonMalaysian['nationality']['restricted']);
        self::assertSame('MY', $legacyNonMalaysian['nationality']['excludedCountryCode']);
    }
}
