<?php

declare(strict_types=1);

namespace Tests\Feature\Election;

use App\Contracts\Clock;
use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ElectionService;
use App\States\ElectionStatus;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ElectionSecurityPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_schedule_creation_is_rejected(): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->post(route('elections.store'), $this->schedulePayload($now, 'Past Schedule Election', -4))
            ->assertSessionHasErrors([
                'nomination_start' => 'The nomination start must be a future date and time.',
            ]);

        $this->assertDatabaseMissing('election', ['title' => 'Past Schedule Election']);
        $this->assertDatabaseMissing('organizer', ['user_id' => $organizer->getKey()]);
    }

    public function test_nomination_start_equal_to_now_is_rejected_by_the_service_boundary(): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer();

        try {
            app(ElectionService::class)->create(
                $organizer,
                $this->schedulePayload($now, 'Equal Start Election', 0),
            );
            self::fail('A nomination start equal to the current time was accepted.');
        } catch (ValidationException $exception) {
            self::assertSame(
                'The nomination start must be a future date and time.',
                $exception->errors()['nomination_start'][0] ?? null,
            );
        }

        $this->assertDatabaseMissing('election', ['title' => 'Equal Start Election']);
        $this->assertDatabaseMissing('organizer', ['user_id' => $organizer->getKey()]);
    }

    public function test_valid_future_schedule_is_accepted(): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->post(route('elections.store'), $this->schedulePayload($now, 'Future Schedule Election', 1))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('election', [
            'title' => 'Future Schedule Election',
            'status' => ElectionStatus::DRAFT->value,
            'nomination_start' => $now->addHour()->toDateTimeString(),
        ]);
    }

    public function test_draft_update_cannot_make_the_schedule_overdue(): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer();
        $service = app(ElectionService::class);
        $election = $service->create(
            $organizer,
            $this->schedulePayload($now, 'Draft Schedule Update', 1),
        );
        $originalStart = $election->nomination_start?->toDateTimeString();

        try {
            $service->update(
                $organizer,
                (int) $election->getKey(),
                $this->schedulePayload($now, 'Draft Schedule Update', -4) + ['version' => 1],
            );
            self::fail('An overdue draft schedule update was accepted.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('nomination_start', $exception->errors());
        }

        $election->refresh();
        self::assertSame(ElectionStatus::DRAFT->value, $election->status);
        self::assertSame(1, $election->version);
        self::assertSame($originalStart, $election->nomination_start?->toDateTimeString());
    }

    /** @param array<string, string> $reasonInput */
    #[DataProvider('invalidCancellationReasonProvider')]
    public function test_form_request_rejects_missing_blank_and_too_short_cancellation_reasons(array $reasonInput): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer('Correct!Pass1');
        $election = $this->draft($organizer, $now, 'Invalid Cancellation Reason');

        $this->actingAs($organizer)
            ->post(route('elections.transition', $election), [
                'action' => 'cancel',
                'current_password' => 'Correct!Pass1',
                'version' => 1,
            ] + $reasonInput)
            ->assertSessionHasErrors('reason');

        $election->refresh();
        self::assertSame(ElectionStatus::DRAFT->value, $election->status);
        self::assertSame(1, $election->version);
    }

    /** @return iterable<string,array{array<string,string>}> */
    public static function invalidCancellationReasonProvider(): iterable
    {
        yield 'missing' => [[]];
        yield 'blank' => [['reason' => '   ']];
        yield 'too short' => [['reason' => 'No']];
    }

    public function test_service_boundary_rejects_an_invalid_cancellation_reason(): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer('Correct!Pass1');
        $election = $this->draft($organizer, $now, 'Service Cancellation Boundary');

        try {
            app(ElectionService::class)->transition(
                $organizer,
                (int) $election->getKey(),
                'cancel',
                'Correct!Pass1',
                ['reason' => 'No', 'version' => 1],
            );
            self::fail('The service accepted an invalid cancellation reason.');
        } catch (BusinessRuleException $exception) {
            self::assertStringContainsString('between 5 and 250 characters', $exception->getMessage());
        }

        $election->refresh();
        self::assertSame(ElectionStatus::DRAFT->value, $election->status);
        self::assertSame(1, $election->version);
    }

    public function test_valid_cancellation_reason_is_stored_in_transition_history(): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer('Correct!Pass1');
        $election = $this->draft($organizer, $now, 'Audited Cancellation Election');
        $reason = 'The event has been postponed by the organizing committee.';

        $this->actingAs($organizer)
            ->post(route('elections.transition', $election), [
                'action' => 'cancel',
                'current_password' => 'Correct!Pass1',
                'reason' => $reason,
                'version' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('elections.show', $election));

        $election->refresh();
        $transition = collect($election->transition_history_json)->last();
        self::assertSame(ElectionStatus::CANCELLED->value, $election->status);
        self::assertSame(2, $election->version);
        self::assertSame('cancel', $transition['action'] ?? null);
        self::assertSame($reason, $transition['metadata']['reason'] ?? null);
    }

    public function test_incorrect_password_shows_an_inline_error_and_leaves_state_and_version_unchanged(): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer('Correct!Pass1');
        $election = $this->draft($organizer, $now, 'Wrong Password Election');
        $showUrl = route('elections.show', $election);

        $response = $this->actingAs($organizer)
            ->from($showUrl)
            ->followingRedirects()
            ->post(route('elections.transition', $election), [
                'action' => 'cancel',
                'current_password' => 'Wrong!Pass1',
                'reason' => 'The organizer requests a safe cancellation.',
                'version' => 1,
            ])
            ->assertOk()
            ->assertSee('The current password is incorrect.')
            ->assertSee('aria-invalid="true"', false)
            ->assertDontSee('Wrong!Pass1');

        $election->refresh();
        self::assertSame(ElectionStatus::DRAFT->value, $election->status);
        self::assertSame(1, $election->version);
        self::assertCount(1, $election->transition_history_json);

    }

    public function test_successful_password_verification_authorizes_only_its_submitted_action(): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer('Correct!Pass1');
        $first = $this->draft($organizer, $now, 'First Action Bound Election');
        $second = $this->draft($organizer, $now, 'Second Action Bound Election');

        $this->actingAs($organizer)
            ->post(route('elections.transition', $first), [
                'action' => 'cancel',
                'current_password' => 'Correct!Pass1',
                'reason' => 'Cancel only the first election.',
                'version' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->post(route('elections.transition', $second), [
            'action' => 'cancel',
            'reason' => 'Attempt a second cancellation without a password.',
            'version' => 1,
        ])->assertSessionHasErrors('current_password');

        self::assertSame(ElectionStatus::CANCELLED->value, $first->refresh()->status);
        self::assertSame(ElectionStatus::DRAFT->value, $second->refresh()->status);
        self::assertSame(1, $second->version);
    }

    public function test_stale_scheduled_public_election_expires_through_an_audited_lifecycle_transition(): void
    {
        $now = CarbonImmutable::parse('2026-09-04 10:00:00');
        $this->bindClock($now);
        $organizer = $this->organizer();
        $organizerRecord = Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizerRecord->getKey(),
            'title' => 'Stale Scheduled Election',
            'position_sought' => 'Representative',
            'description' => 'A scheduled public election whose nomination period has ended.',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => $now->subDays(2),
            'nomination_end' => $now,
            'voting_start' => $now->addDay(),
            'voting_end' => $now->addDays(2),
            'status' => ElectionStatus::SCHEDULED->value,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
            'payment_verified_at' => $now->subDays(3),
            'approved_by' => $organizer->getKey(),
            'approved_at' => $now->subDays(3),
            'transition_history_json' => [],
            'version' => 1,
        ]);

        $summary = app(ElectionService::class)->advanceScheduledElections();

        $election->refresh();
        $transition = collect($election->transition_history_json)->sole();
        self::assertSame(1, $summary['electionsExpired']);
        self::assertSame(ElectionStatus::EXPIRED->value, $election->status);
        self::assertSame(2, $election->version);
        self::assertSame('expire_schedule', $transition['action'] ?? null);
        self::assertSame(ElectionStatus::SCHEDULED->value, $transition['from_state'] ?? null);
        self::assertSame(ElectionStatus::EXPIRED->value, $transition['to_state'] ?? null);
        self::assertNull($transition['actor_user_id'] ?? null);
        self::assertSame('system_schedule', $transition['metadata']['trigger'] ?? null);
        self::assertSame(
            'PUBLIC_NOMINATION_ENDED_BEFORE_PUBLICATION',
            $transition['metadata']['reason'] ?? null,
        );
    }

    private function bindClock(CarbonImmutable $now): Clock
    {
        $clock = new class($now) implements Clock
        {
            public function __construct(private readonly CarbonImmutable $current) {}

            public function now(): CarbonImmutable
            {
                return $this->current;
            }
        };
        $this->app->instance(Clock::class, $clock);

        return $clock;
    }

    private function organizer(string $password = 'password'): User
    {
        return User::factory()->create([
            'password' => Hash::make($password),
            'account_status' => 'ACTIVE',
            'is_verified' => true,
            'verified_at' => now(),
            'is_admin' => false,
        ]);
    }

    private function draft(User $organizer, CarbonImmutable $now, string $title): Election
    {
        return app(ElectionService::class)->create(
            $organizer,
            $this->schedulePayload($now, $title, 1),
        );
    }

    /** @return array<string, mixed> */
    private function schedulePayload(CarbonImmutable $now, string $title, int $startOffsetHours): array
    {
        $nominationStart = $now->addHours($startOffsetHours);

        return [
            'title' => $title,
            'position_sought' => 'Community Representative',
            'description' => 'A sufficiently detailed election description for security validation testing.',
            'eligibility_mode' => EligibilityRules::PUBLIC,
            'eligibility_minimum_age' => 18,
            'eligibility_nationality' => 'ANY',
            'nomination_start' => $nominationStart->toDateTimeString(),
            'nomination_end' => $nominationStart->addHour()->toDateTimeString(),
            'voting_start' => $nominationStart->addHours(2)->toDateTimeString(),
            'voting_end' => $nominationStart->addHours(3)->toDateTimeString(),
        ];
    }
}
