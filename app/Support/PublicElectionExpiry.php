<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Election;
use App\States\ElectionStatus;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Throwable;

/** Central schedule rule for public elections that were never published in time. */
final class PublicElectionExpiry
{
    public const MESSAGE = 'This public election expired because its nomination period ended before payment and administrator approval were completed. Create a new election with a future schedule.';

    /** @var list<string> */
    private const EXPIRABLE_STATES = [
        ElectionStatus::DRAFT->value,
        ElectionStatus::PENDING_PAYMENT->value,
        ElectionStatus::PENDING_ADMIN_APPROVAL->value,
    ];

    public static function shouldExpire(Election $election, CarbonImmutable $now): bool
    {
        if (! in_array((string) $election->status, self::EXPIRABLE_STATES, true)
            || EligibilityRules::fromStored($election->eligibility_rules)->mode() !== EligibilityRules::PUBLIC
            || $election->nomination_end === null
            || $election->nomination_end === '') {
            return false;
        }

        try {
            return $now->gte(CarbonImmutable::parse($election->nomination_end));
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{reason:string,trigger:string,nomination_end:?string} */
    public static function metadata(Election $election, string $trigger): array
    {
        return [
            'reason' => 'PUBLIC_NOMINATION_ENDED_BEFORE_PUBLICATION',
            'trigger' => $trigger,
            'nomination_end' => $election->nomination_end?->toAtomString(),
        ];
    }
}
