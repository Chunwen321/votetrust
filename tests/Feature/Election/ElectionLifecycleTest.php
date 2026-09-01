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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ElectionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_admin_candidate_and_time_gates_control_the_full_lifecycle(): void
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
            'nomination_start' => $clock->now()->subDay(),
            'nomination_end' => $clock->now()->addHour(),
            'voting_start' => $clock->now()->addHours(2),
            'voting_end' => $clock->now()->addHours(4),
        ]);
        self::assertSame(ElectionStatus::DRAFT->value, $election->status);
        self::assertSame('100.00', $election->hosting_fee);

        $election = $service->transition($organizerUser, $election->getKey(), 'submit_for_payment', 'Correct!Pass1', ['version' => 1]);
        self::assertSame(ElectionStatus::PENDING_PAYMENT->value, $election->status);
        self::assertSame('100.00', $election->hosting_fee);

        $election = $service->transition($organizerUser, $election->getKey(), 'submit_payment', 'Correct!Pass1', [
            'version' => 2,
        ]);
        self::assertSame(ElectionStatus::PENDING_ADMIN_APPROVAL->value, $election->status);
        self::assertNotNull($election->payment_verified_at);
        self::assertSame('VT-HOST-PAID-001', $election->payment_reference);
        self::assertSame(1, $gateway->verificationCalls);

        $election = $service->transition($admin, $election->getKey(), 'approve', 'Admin!Pass1', ['version' => 3]);
        self::assertSame(ElectionStatus::NOMINATION_OPEN->value, $election->status);

        try {
            $service->transition($organizerUser, $election->getKey(), 'start_voting', 'Correct!Pass1', ['version' => 4]);
            self::fail('Voting opened without an approved candidate.');
        } catch (BusinessRuleException $exception) {
            self::assertStringContainsString('at least one candidate', $exception->getMessage());
        }

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

        $clock->current = $clock->now()->addHours(2);
        $election = $service->transition($organizerUser, $election->getKey(), 'start_voting', 'Correct!Pass1', ['version' => 4]);
        self::assertSame(ElectionStatus::VOTING_OPEN->value, $election->status);

        try {
            $service->transition($organizerUser, $election->getKey(), 'close_voting', 'Correct!Pass1', ['version' => 5]);
            self::fail('Voting closed before the configured end time.');
        } catch (BusinessRuleException $exception) {
            self::assertStringContainsString('before the configured voting end', $exception->getMessage());
        }

        $clock->current = $clock->now()->addHours(3);
        $election = $service->transition($organizerUser, $election->getKey(), 'close_voting', 'Correct!Pass1', ['version' => 5]);
        self::assertSame(ElectionStatus::CLOSED->value, $election->status);
        self::assertCount(6, $service->transitionLogs($election));
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
