<?php

// PIC: Low Wen Kai

namespace Tests\Feature\Election;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ElectionService;
use App\Services\Payment\HttpPaymentGateway;
use App\States\ElectionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PaymentWebServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('election.payment.url', 'https://payments.example.test/api/v1/payments/verify');
        config()->set('election.payment.api_key', 'payment-contract-secret');
        config()->set('election.payment.timeout', 2);
        config()->set('election.payment.response_clock_skew_seconds', 60);
        Http::preventStrayRequests();
    }

    public function test_it_consumes_the_payment_rest_service_using_the_ifa_envelope(): void
    {
        $requestedAt = CarbonImmutable::parse('2026-08-08T12:00:00+08:00');
        $election = new Election;
        $election->forceFill([
            'id' => 71,
            'organizer_id' => 19,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        Http::fake([
            'https://payments.example.test/*' => Http::response([
                'status' => 'S',
                'message' => 'Hosting payment confirmed.',
                'requestID' => 'EL-PAYMENT-0001',
                'timestamp' => $requestedAt->addSecond()->toAtomString(),
                'data' => [
                    'paymentReference' => 'VT-HOST-PAID-001',
                    'paymentStatus' => 'CONFIRMED',
                    'amount' => 100,
                    'currency' => 'MYR',
                    'payerId' => 19,
                    'electionId' => 71,
                    'purpose' => 'ELECTION_HOSTING',
                    'paymentId' => 982,
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = (new HttpPaymentGateway)->verifyHostingPayment(
            $election,
            null,
            'EL-PAYMENT-0001',
            $requestedAt,
        );

        self::assertTrue($result->paid);
        self::assertSame(982, $result->paymentId);
        Http::assertSent(static function (Request $request) use ($requestedAt): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://payments.example.test/api/v1/payments/verify'
                && ($request->header('X-API-Key')[0] ?? null) === 'payment-contract-secret'
                && $request['requestID'] === 'EL-PAYMENT-0001'
                && $request['timestamp'] === $requestedAt->toAtomString()
                && ! isset($request['paymentReference'])
                && $request['purpose'] === 'ELECTION_HOSTING'
                && $request['payerId'] === 19
                && $request['electionId'] === 71
                && (float) $request['expectedAmount'] === 100.0
                && $request['currency'] === 'MYR';
        });
    }

    public function test_it_rejects_a_payment_response_with_an_invalid_or_stale_timestamp(): void
    {
        $requestedAt = CarbonImmutable::parse('2026-08-08T12:00:00+08:00');
        $election = new Election;
        $election->forceFill([
            'id' => 71,
            'organizer_id' => 19,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        Http::fake([
            '*' => Http::response([
                'status' => 'S',
                'requestID' => 'EL-PAYMENT-0002',
                'timestamp' => $requestedAt->subMinutes(2)->toAtomString(),
                'data' => [
                    'paymentReference' => 'VT-HOST-PAID-001',
                    'paymentStatus' => 'CONFIRMED',
                    'amount' => 100,
                    'currency' => 'MYR',
                    'payerId' => 19,
                    'purpose' => 'ELECTION_HOSTING',
                ],
            ]),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('integration contract');

        (new HttpPaymentGateway)->verifyHostingPayment(
            $election,
            'VT-HOST-PAID-001',
            'EL-PAYMENT-0002',
            $requestedAt,
        );
    }

    public function test_it_rejects_an_unknown_payment_response_status(): void
    {
        $requestedAt = CarbonImmutable::parse('2026-08-08T12:00:00+08:00');
        $election = new Election;
        $election->forceFill([
            'id' => 71,
            'organizer_id' => 19,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        Http::fake([
            '*' => Http::response([
                'status' => 'OK',
                'requestID' => 'EL-PAYMENT-0003',
                'timestamp' => $requestedAt->toAtomString(),
                'data' => [
                    'paymentReference' => 'VT-HOST-PAID-001',
                    'paymentStatus' => 'CONFIRMED',
                    'amount' => 100,
                    'currency' => 'MYR',
                    'payerId' => 19,
                    'purpose' => 'ELECTION_HOSTING',
                ],
            ]),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('integration contract');

        (new HttpPaymentGateway)->verifyHostingPayment(
            $election,
            'VT-HOST-PAID-001',
            'EL-PAYMENT-0003',
            $requestedAt,
        );
    }

    public function test_it_rejects_a_confirmed_payment_linked_to_another_election(): void
    {
        $requestedAt = CarbonImmutable::parse('2026-08-08T12:00:00+08:00');
        $election = new Election;
        $election->forceFill([
            'id' => 71,
            'organizer_id' => 19,
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
        ]);

        Http::fake([
            '*' => Http::response([
                'status' => 'S',
                'requestID' => 'EL-PAYMENT-0004',
                'timestamp' => $requestedAt->toAtomString(),
                'data' => [
                    'paymentReference' => 'VT-HOST-OTHER-001',
                    'paymentStatus' => 'CONFIRMED',
                    'amount' => 100,
                    'currency' => 'MYR',
                    'payerId' => 19,
                    'electionId' => 72,
                    'purpose' => 'ELECTION_HOSTING',
                    'paymentId' => 983,
                ],
            ]),
        ]);

        $result = (new HttpPaymentGateway)->verifyHostingPayment(
            $election,
            null,
            'EL-PAYMENT-0004',
            $requestedAt,
        );

        self::assertFalse($result->paid);
    }

    public function test_organizer_cannot_send_an_unconfirmed_payment_for_admin_review(): void
    {
        Http::fake(static function (Request $request) {
            return Http::response([
                'status' => 'F',
                'message' => 'Payment is pending.',
                'requestID' => $request['requestID'],
                'timestamp' => $request['timestamp'],
                'data' => [
                    'paymentReference' => 'VT-HOST-PENDING-001',
                    'paymentStatus' => 'PENDING',
                    'amount' => $request['expectedAmount'],
                    'currency' => $request['currency'],
                    'payerId' => $request['payerId'],
                    'electionId' => $request['electionId'],
                    'purpose' => 'ELECTION_HOSTING',
                    'paymentId' => 999,
                ],
            ]);
        });

        $organizer = User::query()->create([
            'name' => 'Payment Gate Organizer',
            'email' => 'payment-gate@example.test',
            'password' => Hash::make('Correct!Pass1'),
        ]);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $service = new ElectionService;
        $now = CarbonImmutable::now();
        $election = $service->create($organizer, [
            'title' => 'Payment Boundary Election',
            'position_sought' => 'Council President',
            'description' => 'An election used to verify that payment failures keep the lifecycle closed.',
            'eligibility_mode' => 'PUBLIC',
            'eligibility_minimum_age' => 18,
            'eligibility_nationality' => 'ANY',
            'nomination_start' => $now->addHour(),
            'nomination_end' => $now->addHours(2),
            'voting_start' => $now->addHours(3),
            'voting_end' => $now->addHours(4),
        ]);
        $election = $service->transition(
            $organizer,
            $election->getKey(),
            'submit_for_payment',
            'Correct!Pass1',
            ['version' => 1],
        );
        try {
            $service->transition(
                $organizer,
                $election->getKey(),
                'submit_payment',
                'Correct!Pass1',
                ['version' => 2],
            );
            self::fail('An unconfirmed hosting payment entered the administrator queue.');
        } catch (BusinessRuleException $exception) {
            self::assertStringContainsString('has not been confirmed', $exception->getMessage());
        }

        $election->refresh();
        self::assertSame(ElectionStatus::PENDING_PAYMENT->value, $election->status);
        self::assertSame(2, $election->version);
        self::assertNull($election->payment_verified_at);
        self::assertNull($election->payment_reference);
        self::assertNull($election->payment_response_json);
        self::assertSame(0, DB::table('payment')->count());
        Http::assertSentCount(1);
    }
}
