<?php

// PIC: Low Wen Kai - Election Management Module

namespace Tests\Feature\Election;

use App\Contracts\Clock;
use App\Contracts\PaymentGateway;
use App\Exceptions\BusinessRuleException;
use App\Models\Candidacy;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ElectionService;
use App\Services\Payment\PaymentResult;
use App\States\ElectionStatus;
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
            public function verifyHostingPayment(
                Election $election,
                string $paymentReference,
                string $requestId,
                CarbonImmutable $requestedAt,
            ): PaymentResult {
                return new PaymentResult(true, 'CONFIRMED', $paymentReference, 100, 'MYR', null, [
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
            'name' => 'System Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('Admin!Pass1'),
            'is_admin' => true,
        ]);

        $election = $service->create($organizerUser, [
            'title' => 'Student Council Election',
            'description' => 'A sufficiently detailed election description for lifecycle testing.',
            'eligibility_mode' => 'PUBLIC',
            'eligibility_occupation' => 'ANY',
            'eligibility_nationality' => 'ANY',
            'nomination_start' => $clock->now()->subDays(2),
            'nomination_end' => $clock->now()->subHour(),
            'voting_start' => $clock->now()->subMinutes(30),
            'voting_end' => $clock->now()->addHour(),
            'hosting_fee' => 100,
            'currency' => 'MYR',
        ]);
        self::assertSame(ElectionStatus::DRAFT->value, $election->status);

        $election = $service->transition($organizerUser, $election->getKey(), 'submit_for_payment', 'Correct!Pass1', ['version' => 1]);
        self::assertSame(ElectionStatus::PENDING_PAYMENT->value, $election->status);

        $election = $service->transition($organizerUser, $election->getKey(), 'verify_payment', 'Correct!Pass1', [
            'version' => 2,
            'payment_reference' => 'VT-HOST-PAID-001',
        ]);
        self::assertSame(ElectionStatus::PENDING_ADMIN_APPROVAL->value, $election->status);
        self::assertNotNull($election->payment_verified_at);

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
        Candidate::query()->create(['user_id' => $candidateUser->getKey()]);
        Candidacy::query()->create([
            'candidate_id' => $candidateUser->getKey(),
            'election_id' => $election->getKey(),
            'status' => Candidacy::APPROVED,
        ]);

        $election = $service->transition($organizerUser, $election->getKey(), 'start_voting', 'Correct!Pass1', ['version' => 4]);
        self::assertSame(ElectionStatus::VOTING_OPEN->value, $election->status);

        try {
            $service->transition($organizerUser, $election->getKey(), 'close_voting', 'Correct!Pass1', ['version' => 5]);
            self::fail('Voting closed before the configured end time.');
        } catch (BusinessRuleException $exception) {
            self::assertStringContainsString('before the configured voting end', $exception->getMessage());
        }

        $clock->current = $clock->now()->addHours(2);
        $election = $service->transition($organizerUser, $election->getKey(), 'close_voting', 'Correct!Pass1', ['version' => 5]);
        self::assertSame(ElectionStatus::CLOSED->value, $election->status);
        self::assertCount(6, $service->transitionLogs($election));
    }
}
