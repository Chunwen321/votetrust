<?php

namespace Tests\Unit;

use App\Services\CandidateRegistration\PaymentStatusClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PIC: Wong Shun Bin
 * Verifies real HTTP consumption, correlation, and fail-closed IFA validation.
 */
final class CandidatePaymentStatusClientTest extends TestCase
{
    public function test_it_consumes_a_correlated_payment_status_response(): void
    {
        $this->configureClient();

        Http::fake(function (Request $request) {
            return Http::response([
                'status' => 'S',
                'message' => 'Candidate deposit status retrieved.',
                'requestID' => $request['requestID'],
                'timestamp' => now()->toAtomString(),
                'data' => [
                    'paymentId' => 91,
                    'candidateId' => 27,
                    'paymentStatus' => 'CONFIRMED',
                ],
            ]);
        });

        $result = $this->app->make(PaymentStatusClient::class)->fetch(91, 27);

        $this->assertTrue($result->available);
        $this->assertTrue($result->paid);
        $this->assertSame('CONFIRMED', $result->status);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://payment.test/api/v1/payments/91/status')
            && $request->hasHeader('X-Payment-Api-Key', 'payment-consumer-secret')
            && $request['candidateId'] === 27
            && is_string($request['requestID'])
            && str_starts_with($request['requestID'], 'can_')
            && is_string($request['timestamp']));
    }

    public function test_it_fails_closed_when_the_payment_response_is_stale_or_mismatched(): void
    {
        $this->configureClient();

        Http::fake(function (Request $request) {
            return Http::response([
                'status' => 'S',
                'message' => 'Candidate deposit status retrieved.',
                'requestID' => $request['requestID'],
                'timestamp' => now()->subHour()->toAtomString(),
                'data' => [
                    'paymentId' => 999,
                    'candidateId' => 27,
                    'paymentStatus' => 'CONFIRMED',
                ],
            ]);
        });

        $result = $this->app->make(PaymentStatusClient::class)->fetch(91, 27);

        $this->assertFalse($result->available);
        $this->assertFalse($result->paid);
        $this->assertSame('UNAVAILABLE', $result->status);
    }

    public function test_it_does_not_send_a_request_without_explicit_credentials(): void
    {
        config([
            'candidate.services.payment.base_url' => '',
            'candidate.services.payment.api_key' => '',
        ]);
        Http::fake();

        $result = $this->app->make(PaymentStatusClient::class)->fetch(91, 27);

        $this->assertFalse($result->available);
        Http::assertNothingSent();
    }

    private function configureClient(): void
    {
        config([
            'candidate.services.payment.base_url' => 'https://payment.test',
            'candidate.services.payment.api_key' => 'payment-consumer-secret',
            'candidate.services.payment.connect_timeout_seconds' => 1,
            'candidate.services.payment.timeout_seconds' => 2,
            'candidate.services.payment.response_max_age_seconds' => 300,
        ]);
    }
}
