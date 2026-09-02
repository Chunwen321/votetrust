<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Election;
use App\Support\ElectionCardMilestone;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ElectionCardMilestoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Kuala_Lumpur']);
    }

    /** @return iterable<string, array{string}> */
    public static function inclusiveNominationBoundaries(): iterable
    {
        yield 'the opening instant' => ['2026-08-15 09:00:00'];
        yield 'the closing instant' => ['2026-08-15 17:00:00'];
    }

    #[DataProvider('inclusiveNominationBoundaries')]
    public function test_candidate_nomination_window_is_active_at_both_inclusive_boundaries(string $now): void
    {
        $milestone = ElectionCardMilestone::forCandidate(
            Election::NOMINATION_OPEN,
            '2026-08-15 09:00:00',
            '2026-08-15 17:00:00',
            '2026-08-16 09:00:00',
            '2026-08-16 17:00:00',
            $this->at($now),
        );

        self::assertSame('Nominations close', $milestone->label);
        self::assertSame('active', $milestone->tone);
        self::assertSame('2026-08-15T17:00:00+08:00', $milestone->machineAt());
    }

    /** @return iterable<string, array{string}> */
    public static function inclusiveVotingBoundaries(): iterable
    {
        yield 'the opening instant' => ['2026-08-16 09:00:00'];
        yield 'the closing instant' => ['2026-08-16 17:00:00'];
    }

    #[DataProvider('inclusiveVotingBoundaries')]
    public function test_voter_voting_window_is_active_at_both_inclusive_boundaries(string $now): void
    {
        $milestone = ElectionCardMilestone::forVoter(
            Election::VOTING_OPEN,
            '2026-08-16 09:00:00',
            '2026-08-16 17:00:00',
            $this->at($now),
        );

        self::assertSame('Voting closes', $milestone->label);
        self::assertSame('active', $milestone->tone);
        self::assertSame('2026-08-16T17:00:00+08:00', $milestone->machineAt());
    }

    public function test_pre_nomination_organizer_states_describe_the_nomination_date_as_planned(): void
    {
        foreach ([Election::DRAFT, Election::PENDING_PAYMENT, Election::PENDING_ADMIN_APPROVAL, Election::SCHEDULED] as $status) {
            $milestone = ElectionCardMilestone::forOrganizer(
                $status,
                '2026-08-16 09:00:00',
                '2026-08-17 09:00:00',
                '2026-08-18 09:00:00',
                '2026-08-19 09:00:00',
                now: $this->at('2026-08-15 09:00:00'),
            );

            self::assertSame('Planned nomination start', $milestone->label, "Unexpected wording for {$status}.");
            self::assertSame('upcoming', $milestone->tone);
            self::assertSame('2026-08-16T09:00:00+08:00', $milestone->machineAt());
        }
    }

    public function test_pre_nomination_organizer_states_warn_when_the_planned_nomination_start_has_passed(): void
    {
        foreach ([Election::DRAFT, Election::PENDING_PAYMENT, Election::PENDING_ADMIN_APPROVAL, Election::SCHEDULED] as $status) {
            $milestone = ElectionCardMilestone::forOrganizer(
                $status,
                '2026-08-14 09:00:00',
                '2026-08-17 09:00:00',
                '2026-08-18 09:00:00',
                '2026-08-19 09:00:00',
                now: $this->at('2026-08-15 09:00:00'),
            );

            self::assertSame('Nomination start passed', $milestone->label, "Unexpected wording for {$status}.");
            self::assertSame('attention', $milestone->tone);
            self::assertSame('2026-08-14T09:00:00+08:00', $milestone->machineAt());
        }
    }

    public function test_nomination_closed_state_reports_the_automatic_voting_transition(): void
    {
        $beforeVoting = ElectionCardMilestone::forOrganizer(
            Election::NOMINATION_CLOSED,
            '2026-08-13 09:00:00',
            '2026-08-14 17:00:00',
            '2026-08-16 09:00:00',
            '2026-08-16 17:00:00',
            now: $this->at('2026-08-15 09:00:00'),
        );

        self::assertSame('Voting opens automatically', $beforeVoting->label);
        self::assertSame('upcoming', $beforeVoting->tone);
        self::assertSame('2026-08-16T09:00:00+08:00', $beforeVoting->machineAt());

        $readyForAutomaticTransition = ElectionCardMilestone::forOrganizer(
            Election::NOMINATION_CLOSED,
            '2026-08-13 09:00:00',
            '2026-08-14 17:00:00',
            '2026-08-16 09:00:00',
            '2026-08-16 17:00:00',
            now: $this->at('2026-08-16 09:00:00'),
        );

        self::assertSame('Automatic voting start due', $readyForAutomaticTransition->label);
        self::assertSame('attention', $readyForAutomaticTransition->tone);
        self::assertSame('2026-08-16T09:00:00+08:00', $readyForAutomaticTransition->machineAt());
    }

    public function test_overdue_voting_open_state_calls_out_the_due_system_closure(): void
    {
        $now = $this->at('2026-08-16 17:00:01');

        $organizer = ElectionCardMilestone::forOrganizer(
            Election::VOTING_OPEN,
            null,
            null,
            '2026-08-16 09:00:00',
            '2026-08-16 17:00:00',
            now: $now,
        );
        $voter = ElectionCardMilestone::forVoter(
            Election::VOTING_OPEN,
            '2026-08-16 09:00:00',
            '2026-08-16 17:00:00',
            $now,
        );

        self::assertStringContainsString('system closure due', $organizer->label);
        self::assertSame('attention', $organizer->tone);
        self::assertSame('2026-08-16T17:00:00+08:00', $organizer->machineAt());
        self::assertStringContainsString('system closure due', $voter->label);
        self::assertSame('attention', $voter->tone);
        self::assertSame('2026-08-16T17:00:00+08:00', $voter->machineAt());
    }

    public function test_closed_state_reports_the_configured_voting_end_as_past(): void
    {
        $milestone = ElectionCardMilestone::forCandidate(
            Election::CLOSED,
            '2026-08-13 09:00:00',
            '2026-08-14 09:00:00',
            '2026-08-15 09:00:00',
            '2026-08-15 17:00:00',
            $this->at('2026-08-16 09:00:00'),
        );

        self::assertSame('Voting ended', $milestone->label);
        self::assertSame('past', $milestone->tone);
        self::assertSame('2026-08-15T17:00:00+08:00', $milestone->machineAt());
    }

    public function test_closed_voter_state_without_an_end_date_fails_closed(): void
    {
        $milestone = ElectionCardMilestone::forVoter(
            Election::CLOSED,
            '2026-08-20 09:00:00',
            null,
            $this->at('2026-08-15 09:00:00'),
        );

        self::assertSame('Schedule unavailable', $milestone->label);
        self::assertSame('unavailable', $milestone->tone);
        self::assertNull($milestone->at);
    }

    public function test_terminal_voter_states_do_not_make_a_false_future_voting_promise(): void
    {
        foreach ([Election::CANCELLED, Election::REJECTED] as $status) {
            $milestone = ElectionCardMilestone::forVoter(
                $status,
                '2026-08-20 09:00:00',
                '2026-08-20 17:00:00',
                $this->at('2026-08-15 09:00:00'),
            );

            self::assertSame('Schedule unavailable', $milestone->label, "Unexpected wording for {$status}.");
            self::assertSame('unavailable', $milestone->tone);
            self::assertNull($milestone->at);
            self::assertNull($milestone->formattedAt());
            self::assertNull($milestone->machineAt());
        }
    }

    public function test_null_and_invalid_dates_fail_closed_without_throwing(): void
    {
        $now = $this->at('2026-08-15 09:00:00');

        $voter = ElectionCardMilestone::forVoter(Election::VOTING_OPEN, 'not-a-date', null, $now);
        $candidate = ElectionCardMilestone::forCandidate(
            Election::NOMINATION_OPEN,
            null,
            'not-a-date',
            null,
            'also-not-a-date',
            $now,
        );
        $organizer = ElectionCardMilestone::forOrganizer(
            Election::DRAFT,
            'not-a-date',
            null,
            null,
            null,
            now: $now,
        );

        foreach ([$voter, $candidate, $organizer] as $milestone) {
            self::assertSame('Schedule unavailable', $milestone->label);
            self::assertSame('unavailable', $milestone->tone);
            self::assertNull($milestone->at);
        }
    }

    public function test_dates_are_converted_to_the_configured_display_timezone(): void
    {
        $milestone = ElectionCardMilestone::forVoter(
            Election::VOTING_OPEN,
            '2026-08-15T05:00:00+00:00',
            '2026-08-15T06:30:00+00:00',
            $this->at('2026-08-15 14:00:00'),
        );

        self::assertSame('Asia/Kuala_Lumpur', $milestone->at?->timezoneName);
        self::assertSame('15 Aug 2026, 2:30 PM', $milestone->formattedAt());
        self::assertSame('2026-08-15T14:30:00+08:00', $milestone->machineAt());
    }

    public function test_timezone_label_is_derived_from_the_configured_timezone(): void
    {
        self::assertSame('Malaysia Time (UTC+8)', ElectionCardMilestone::timezoneLabel());

        config(['app.timezone' => 'UTC']);
        self::assertSame('UTC', ElectionCardMilestone::timezoneLabel());

        config(['app.timezone' => 'Asia/Tokyo']);
        self::assertSame('Asia/Tokyo', ElectionCardMilestone::timezoneLabel());
    }

    private function at(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'Asia/Kuala_Lumpur');
    }
}
