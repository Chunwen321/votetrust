<?php

// PIC: Low Wen Kai - Election Management Module

namespace Tests\Feature\Election;

use App\Contracts\Clock;
use App\Contracts\PaymentGateway;
use App\Exceptions\BusinessRuleException;
use App\Models\Candidacy;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ElectionService;
use App\Services\Payment\PaymentResult;
use App\States\ElectionStatus;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ElectionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_payment_steps_do_not_require_password_but_nomination_start_does(): void
    {
        $organizer = User::factory()->create([
            'password' => Hash::make('Correct!Pass1'),
            'account_status' => 'ACTIVE',
            'is_verified' => true,
            'verified_at' => now(),
        ]);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $service = app(ElectionService::class);
        $now = CarbonImmutable::now();
        $schedule = [
            'title' => 'Password Boundary Election',
            'position_sought' => 'Council President',
            'description' => 'An election used to verify the organizer password boundary.',
            'eligibility_mode' => 'PUBLIC',
            'eligibility_minimum_age' => 18,
            'eligibility_nationality' => 'ANY',
            'nomination_start' => $now->addHour(),
            'nomination_end' => $now->addHours(2),
            'voting_start' => $now->addHours(3),
            'voting_end' => $now->addHours(4),
        ];

        $paymentElection = $service->create($organizer, $schedule);
        $this->actingAs($organizer)
            ->post(route('elections.transition', $paymentElection), [
                'action' => 'submit_for_payment',
                'version' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('elections.show', $paymentElection));
        self::assertSame(ElectionStatus::PENDING_PAYMENT->value, $paymentElection->refresh()->status);

        $scheduledElection = $service->create($organizer, $schedule + ['title' => 'Scheduled Password Boundary']);
        $scheduledElection->forceFill([
            'status' => ElectionStatus::SCHEDULED->value,
            'nomination_start' => $now->subMinute(),
            'payment_verified_at' => $now,
            'approved_by' => $organizer->getKey(),
            'approved_at' => $now,
        ])->save();

        $this->post(route('elections.transition', $scheduledElection), [
            'action' => 'start_nomination',
            'version' => 1,
        ])->assertSessionHasErrors('current_password');
        self::assertSame(ElectionStatus::SCHEDULED->value, $scheduledElection->refresh()->status);
    }

    public function test_nomination_start_is_confirmed_once_and_later_schedule_transitions_are_automatic(): void
    {
        $clock = new class(CarbonImmutable::parse('2026-08-08 12:00:00')) implements Clock
        {
            public function __construct(public CarbonImmutable $current) {}

            public function now(): CarbonImmutable
            {
                return $this->current;
            }
        };
        $gateway = new class implements PaymentGateway
        {
            public int $verificationCalls = 0;

            public function verifyHostingPayment(
                Election $election,
                ?string $paymentReference,
                string $requestId,
                CarbonImmutable $requestedAt,
            ): PaymentResult {
                $this->verificationCalls++;

                return new PaymentResult(true, 'CONFIRMED', 'VT-HOST-PAID-001', 100, 'MYR', 982, [
                    'status' => 'S',
                    'requestID' => $requestId,
                    'data' => ['paymentStatus' => 'CONFIRMED'],
                ]);
            }
        };
        $service = new ElectionService(paymentGateway: $gateway, clock: $clock);
        $organizerUser = User::query()->create([
            'name' => 'Test Organizer',
            'email' => 'organizer@example.test',
            'password' => Hash::make('Correct!Pass1'),
        ]);
        Organizer::query()->create(['user_id' => $organizerUser->getKey()]);
        $admin = User::query()->create([
            'name' => 'Test Administrator',
            'email' => 'admin@example.test',
            'password' => Hash::make('Admin!Pass1'),
            'is_admin' => true,
        ]);

        $election = $service->create($organizerUser, [
            'title' => 'Student Council Election',
            'position_sought' => 'Student Council Representative',
            'description' => 'A sufficiently detailed election description for lifecycle testing.',
            'eligibility_mode' => 'PUBLIC',
            'eligibility_minimum_age' => 18,
            'eligibility_nationality' => 'ANY',
            'nomination_start' => $clock->now()->addHour(),
            'nomination_end' => $clock->now()->addHours(2),
            'voting_start' => $clock->now()->addHours(3),
            'voting_end' => $clock->now()->addHours(5),
        ]);
        self::assertSame(ElectionStatus::DRAFT->value, $election->status);
        self::assertSame('100.00', $election->hosting_fee);

        $election = $service->transition($organizerUser, $election->getKey(), 'submit_for_payment', '', ['version' => 1]);
        self::assertSame(ElectionStatus::PENDING_PAYMENT->value, $election->status);
        self::assertSame('100.00', $election->hosting_fee);

        $election = $service->transition($organizerUser, $election->getKey(), 'submit_payment', '', [
            'version' => 2,
        ]);
        self::assertSame(ElectionStatus::PENDING_ADMIN_APPROVAL->value, $election->status);
        self::assertNotNull($election->payment_verified_at);
        self::assertSame('VT-HOST-PAID-001', $election->payment_reference);
        self::assertSame(1, $gateway->verificationCalls);

        $election = $service->transition($admin, $election->getKey(), 'approve', 'Admin!Pass1', ['version' => 3]);
        self::assertSame(ElectionStatus::SCHEDULED->value, $election->status);
        self::assertSame(['start_nomination', 'cancel'], array_column($service->actions($election, $organizerUser), 'action'));

        try {
            $service->transition($organizerUser, $election->getKey(), 'start_nomination', 'Correct!Pass1', ['version' => 4]);
            self::fail('Nominations started before the configured start time.');
        } catch (BusinessRuleException $exception) {
            self::assertStringContainsString('before the configured nomination start', $exception->getMessage());
        }

        $clock->current = $clock->now()->addHour();
        $election = $service->transition($organizerUser, $election->getKey(), 'start_nomination', 'Correct!Pass1', ['version' => 4]);
        self::assertSame(ElectionStatus::NOMINATION_OPEN->value, $election->status);

        self::assertSame(['cancel'], array_column($service->actions($election, $organizerUser), 'action'));

        $clock->current = $clock->now()->addHours(2);
        $summary = $service->advanceScheduledElections();
        self::assertSame(1, $summary['nominationsClosed']);
        self::assertSame(1, $summary['votingBlocked']);
        self::assertSame(ElectionStatus::NOMINATION_CLOSED->value, $election->refresh()->status);

        $candidateUser = User::query()->create([
            'name' => 'Approved Candidate',
            'email' => 'candidate@example.test',
            'password' => Hash::make('Candidate!Pass1'),
        ]);
        Candidacy::query()->create([
            'user_id' => $candidateUser->getKey(),
            'election_id' => $election->getKey(),
            'status' => Candidacy::APPROVED,
        ]);

        $summary = $service->advanceScheduledElections();
        self::assertSame(1, $summary['votingOpened']);
        $election->refresh();
        self::assertSame(ElectionStatus::VOTING_OPEN->value, $election->status);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('controlled by the election scheduler');
        $service->transition($organizerUser, $election->getKey(), 'close_voting', 'Correct!Pass1', ['version' => 7]);
    }

    public function test_scheduler_closes_voting_only_when_the_configured_end_is_reached(): void
    {
        $clock = new class(CarbonImmutable::parse('2026-08-08 15:00:00')) implements Clock
        {
            public function __construct(public CarbonImmutable $current) {}

            public function now(): CarbonImmutable
            {
                return $this->current;
            }
        };
        $service = new ElectionService(clock: $clock);
        $organizer = User::factory()->create();
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Scheduled Closing Election',
            'status' => ElectionStatus::VOTING_OPEN->value,
            'voting_start' => $clock->now()->subHour(),
            'voting_end' => $clock->now()->addHours(2),
            'version' => 1,
            'transition_history_json' => [],
        ]);

        $summary = $service->advanceScheduledElections();
        self::assertSame(0, $summary['electionsClosed']);
        self::assertSame(ElectionStatus::VOTING_OPEN->value, $election->refresh()->status);

        $clock->current = $clock->now()->addHours(2);
        $summary = $service->advanceScheduledElections();
        self::assertSame(1, $summary['electionsClosed']);
        $election->refresh();
        self::assertSame(ElectionStatus::CLOSED->value, $election->status);
        self::assertCount(1, $service->transitionLogs($election));
        self::assertSame([
            'close_voting',
        ], collect($service->transitionLogs($election, false))
            ->map(static fn ($record): mixed => $record->get('action'))
            ->values()
            ->all());
    }

    public function test_overdue_public_pending_payment_expires_without_calling_the_payment_gateway(): void
    {
        $clock = new class(CarbonImmutable::parse('2026-08-08 12:00:00')) implements Clock
        {
            public function __construct(public CarbonImmutable $current) {}

            public function now(): CarbonImmutable
            {
                return $this->current;
            }
        };
        $gateway = new class implements PaymentGateway
        {
            public int $verificationCalls = 0;

            public function verifyHostingPayment(
                Election $election,
                ?string $paymentReference,
                string $requestId,
                CarbonImmutable $requestedAt,
            ): PaymentResult {
                $this->verificationCalls++;

                throw new \RuntimeException('The Payment API must not be called for an expired election.');
            }
        };
        $service = new ElectionService(paymentGateway: $gateway, clock: $clock);
        $organizer = User::factory()->create(['password' => Hash::make('Correct!Pass1')]);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Expired Public Payment Election',
            'position_sought' => 'President',
            'description' => 'A public election whose nomination schedule ended before payment.',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => $clock->now()->subDays(2),
            'nomination_end' => $clock->now(),
            'voting_start' => $clock->now()->addDay(),
            'voting_end' => $clock->now()->addDays(2),
            'status' => Election::PENDING_PAYMENT,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
            'transition_history_json' => [],
            'version' => 1,
        ]);

        $expired = $service->transition(
            $organizer,
            (int) $election->getKey(),
            'submit_payment',
            'Correct!Pass1',
            ['version' => 1],
        );

        self::assertSame(Election::EXPIRED, $expired->status);
        self::assertSame(0, $gateway->verificationCalls);
        self::assertNull($expired->payment_verified_at);
        self::assertSame('expire_schedule', $service->transitionLogs($expired)[0]->get('action'));
        self::assertSame('PUBLIC_NOMINATION_ENDED_BEFORE_PUBLICATION', $service->transitionLogs($expired)[0]->get('metadata')['reason']);
        self::assertSame([], $service->actions($expired, $organizer));
    }
}
