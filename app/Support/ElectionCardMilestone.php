<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Election;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * A role-aware date shown on an election workspace card.
 *
 * Election lifecycle transitions are manual, so the label is derived from
 * both the saved state and the clock instead of assuming a date changes state.
 */
final readonly class ElectionCardMilestone
{
    public function __construct(
        public string $label,
        public ?CarbonImmutable $at,
        public string $tone,
    ) {}

    public static function forVoter(
        string $status,
        mixed $votingStart,
        mixed $votingEnd,
        ?CarbonImmutable $now = null,
    ): self {
        $now ??= self::now();
        $start = self::date($votingStart);
        $end = self::date($votingEnd);

        if ($status === Election::VOTING_OPEN) {
            if ($start !== null && $now->lt($start)) {
                return new self('Voting opens', $start, 'upcoming');
            }

            if ($end !== null && $now->lte($end)) {
                return new self('Voting closes', $end, 'active');
            }

            if ($end !== null) {
                return new self('Voting ended — awaiting closure', $end, 'attention');
            }

            if ($start !== null) {
                return new self('Voting opened', $start, 'active');
            }
        }

        if ($status === Election::CLOSED) {
            return $end === null
                ? self::unavailable()
                : new self('Voting ended', $end, 'past');
        }

        if (in_array($status, [Election::CANCELLED, Election::EXPIRED, Election::REJECTED], true)) {
            return self::unavailable();
        }

        if ($start !== null) {
            if ($now->lt($start)) {
                return new self(
                    $status === Election::NOMINATION_OPEN ? 'Voting opens' : 'Voting scheduled',
                    $start,
                    'upcoming',
                );
            }

            return new self(
                $status === Election::NOMINATION_OPEN ? 'Voting start passed' : 'Voting scheduled since',
                $start,
                'attention',
            );
        }

        return self::unavailable();
    }

    public static function forCandidate(
        string $status,
        mixed $nominationStart,
        mixed $nominationEnd,
        mixed $votingStart,
        mixed $votingEnd,
        ?CarbonImmutable $now = null,
    ): self {
        $now ??= self::now();
        $nominationStart = self::date($nominationStart);
        $nominationEnd = self::date($nominationEnd);

        if ($status === Election::NOMINATION_OPEN) {
            if ($nominationStart !== null && $now->lt($nominationStart)) {
                return new self('Nominations open', $nominationStart, 'upcoming');
            }

            if ($nominationEnd !== null && $now->lte($nominationEnd)) {
                return new self('Nominations close', $nominationEnd, 'active');
            }

            if ($nominationEnd !== null) {
                return new self('Nominations closed', $nominationEnd, 'attention');
            }

            if ($nominationStart !== null) {
                return new self('Nominations opened', $nominationStart, 'active');
            }
        }

        return self::votingMilestone($status, $votingStart, $votingEnd, $now);
    }

    public static function forOrganizer(
        string $status,
        mixed $nominationStart,
        mixed $nominationEnd,
        mixed $votingStart,
        mixed $votingEnd,
        mixed $rejectedAt = null,
        ?CarbonImmutable $now = null,
    ): self {
        $now ??= self::now();
        $nominationStart = self::date($nominationStart);
        $nominationEnd = self::date($nominationEnd);
        $votingStart = self::date($votingStart);
        $votingEnd = self::date($votingEnd);

        if (in_array($status, [Election::DRAFT, Election::PENDING_PAYMENT, Election::PENDING_ADMIN_APPROVAL], true)) {
            if ($nominationStart === null) {
                return self::unavailable();
            }

            return $now->lt($nominationStart)
                ? new self('Planned nomination start', $nominationStart, 'upcoming')
                : new self('Nomination start passed', $nominationStart, 'attention');
        }

        if ($status === Election::NOMINATION_OPEN) {
            if ($nominationStart !== null && $now->lt($nominationStart)) {
                return new self('Nominations open', $nominationStart, 'upcoming');
            }

            if ($nominationEnd !== null && $now->lte($nominationEnd)) {
                return new self('Nominations close', $nominationEnd, 'active');
            }

            if ($votingStart !== null && $now->lt($votingStart)) {
                return new self('Voting opens', $votingStart, 'upcoming');
            }

            if ($votingStart !== null) {
                return new self('Voting ready since', $votingStart, 'attention');
            }

            if ($nominationEnd !== null) {
                return new self('Nominations closed', $nominationEnd, 'attention');
            }
        }

        if (in_array($status, [Election::VOTING_OPEN, Election::CLOSED], true)) {
            return self::votingMilestone($status, $votingStart, $votingEnd, $now);
        }

        if ($status === Election::REJECTED) {
            return new self('Rejected', self::date($rejectedAt), 'past');
        }

        if ($status === Election::CANCELLED) {
            return new self('Cancelled', null, 'past');
        }

        if ($status === Election::EXPIRED) {
            return new self('Schedule expired', $nominationEnd, 'past');
        }

        return self::unavailable();
    }

    public function formattedAt(): ?string
    {
        return $this->at?->format('d M Y, g:i A');
    }

    public function machineAt(): ?string
    {
        return $this->at?->toIso8601String();
    }

    public static function timezoneLabel(): string
    {
        $timezone = self::timezone();

        return match ($timezone) {
            'Asia/Kuala_Lumpur' => 'Malaysia Time (UTC+8)',
            'UTC', 'Etc/UTC' => 'UTC',
            default => str_replace('_', ' ', $timezone),
        };
    }

    private static function votingMilestone(
        string $status,
        mixed $votingStart,
        mixed $votingEnd,
        CarbonImmutable $now,
    ): self {
        $start = self::date($votingStart);
        $end = self::date($votingEnd);

        if ($status === Election::CLOSED) {
            return $end === null
                ? self::unavailable()
                : new self('Voting ended', $end, 'past');
        }

        if ($status === Election::VOTING_OPEN) {
            if ($start !== null && $now->lt($start)) {
                return new self('Voting opens', $start, 'upcoming');
            }

            if ($end !== null && $now->lte($end)) {
                return new self('Voting closes', $end, 'active');
            }

            if ($end !== null) {
                return new self('Voting ended — close election', $end, 'attention');
            }

            if ($start !== null) {
                return new self('Voting opened', $start, 'active');
            }
        }

        return self::unavailable();
    }

    private static function unavailable(): self
    {
        return new self('Schedule unavailable', null, 'unavailable');
    }

    private static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
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

            return $date->setTimezone(self::timezone());
        } catch (Throwable) {
            return null;
        }
    }

    private static function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Kuala_Lumpur');
    }
}
