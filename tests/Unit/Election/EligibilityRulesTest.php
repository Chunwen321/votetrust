<?php

// PIC: Low Wen Kai - Election Management Module

namespace Tests\Unit\Election;

use App\ValueObjects\EligibilityRules;
use PHPUnit\Framework\TestCase;

final class EligibilityRulesTest extends TestCase
{
    public function test_public_and_restricted_rules_round_trip_through_storage(): void
    {
        $public = EligibilityRules::fromStored(EligibilityRules::publicElection()->toStorage());
        self::assertSame(EligibilityRules::PUBLIC, $public->mode());

        $restricted = EligibilityRules::restricted(18, 'STUDENT', 'MALAYSIAN');
        $restored = EligibilityRules::fromStored($restricted->toStorage());
        self::assertSame(18, $restored->minimumAge());
        self::assertSame('STUDENT', $restored->occupation());
        self::assertSame('MALAYSIAN', $restored->nationality());
    }

    public function test_legacy_plain_text_rules_remain_readable(): void
    {
        self::assertSame('Verified members only', EligibilityRules::fromStored('Verified members only')->summary());
    }
}
