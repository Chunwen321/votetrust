<?php

namespace Tests\Unit;

use App\Services\CandidateRegistration\CandidateDepositRefundService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CandidateDepositRefundServiceTest extends TestCase
{
    public function test_it_uses_the_official_tally_and_requests_an_eligible_refund(): void
    {
        $this->configureServices();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/tally')) {
                return Http::response([
                    'status' => 'S',
                    'message' => 'Vote tally retrieved.',
                    'requestID' => $request['requestID'],
                    'timestamp' => now()->toAtomString(),
                    'data' => [
                        'electionId' => 12,
                        'totalVotes' => 100,
                        'tally' => [['candidateId' => 27, 'candidateName' => 'Candidate', 'votes' => 6]],
                    ],
                ]);
            }

            return Http::response([
                'status' => 'S',
                'message' => 'Candidate deposit refunded.',
                'requestID' => $request['requestID'],
                'timestamp' => now()->toAtomString(),
                'data' => ['paymentStatus' => 'REFUNDED'],
            ]);
        });

        $result = $this->app->make(CandidateDepositRefundService::class)->refund(91, 27, 12);

        self::assertSame(6, $result['candidateVotes']);
        self::assertSame(100, $result['totalVotes']);
        self::assertSame(6.0, $result['votePercentage']);
        Http::assertSentCount(2);
    }

    public function test_exactly_five_percent_is_not_eligible_and_payment_is_not_called(): void
    {
        $this->configureServices();
        Http::fake(fn (Request $request) => Http::response([
            'status' => 'S',
            'message' => 'Vote tally retrieved.',
            'requestID' => $request['requestID'],
            'timestamp' => now()->toAtomString(),
            'data' => [
                'electionId' => 12,
                'totalVotes' => 100,
                'tally' => [['candidateId' => 27, 'candidateName' => 'Candidate', 'votes' => 5]],
            ],
        ]));

        try {
            $this->app->make(CandidateDepositRefundService::class)->refund(91, 27, 12);
            self::fail('Exactly 5% incorrectly qualified for a refund.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('refund', $exception->errors());
        }

        Http::assertSentCount(1);
    }

    private function configureServices(): void
    {
        config([
            'candidate.services.voting.base_url' => 'https://voting.test',
            'candidate.services.voting.api_key' => 'voting-secret',
            'candidate.services.payment.base_url' => 'https://payment.test',
            'candidate.services.payment.api_key' => 'payment-secret',
        ]);
    }
}
