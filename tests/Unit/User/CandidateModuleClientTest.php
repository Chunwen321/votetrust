<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use App\Services\User\CandidateModuleClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CandidateModuleClientTest extends TestCase
{
    public function test_all_required_candidate_functions_use_authenticated_rest_requests(): void
    {
        $now = CarbonImmutable::parse('2026-08-15 12:00:00', config('app.timezone'));
        $this->travelTo($now);
        config()->set('votetrust-user.services.candidate', [
            'base_url' => 'https://candidate.test',
            'api_key' => 'candidate-consumer-key',
            'connect_timeout_seconds' => 1,
            'timeout_seconds' => 2,
            'response_max_age_seconds' => 300,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) use ($now): mixed {
            $input = $this->requestInput($request);
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $data = match (true) {
                $request->method() === 'GET' && $path === '/api/v1/elections/14/candidates' => [
                    'electionId' => 14,
                    'candidates' => [['userId' => 22, 'status' => 'Approved', 'registeredAt' => $now->toAtomString()]],
                ],
                $request->method() === 'GET' && $path === '/api/v1/candidates/metrics' => [
                    'candidates' => 1,
                    'pending' => 0,
                ],
                default => [
                    'electionId' => 14,
                    'userId' => 22,
                    'status' => 'Approved',
                ],
            };

            return Http::response([
                'status' => 'S',
                'message' => 'Candidate data retrieved.',
                'requestID' => $input['requestID'] ?? null,
                'timestamp' => $now->toAtomString(),
                'data' => $data,
            ]);
        });

        $client = app(CandidateModuleClient::class);

        self::assertSame(22, $client->participants(14)[0]['userId'] ?? null);
        self::assertSame(1, $client->metrics()['candidates'] ?? null);
        self::assertTrue($client->saveParticipant(14, 22, 'Approved'));
        self::assertTrue($client->changeParticipantStatus(14, 22, 'Approved'));

        Http::assertSentCount(4);
        foreach (Http::recorded() as [$request]) {
            $input = $this->requestInput($request);

            self::assertTrue(in_array($request->method(), ['GET', 'POST', 'PATCH'], true));
            self::assertTrue($request->hasHeader('X-Internal-Api-Key', 'candidate-consumer-key'));
            self::assertMatchesRegularExpression('/\Ausr_[a-f0-9]{32}\z/', (string) ($input['requestID'] ?? ''));
            self::assertSame($now->toAtomString(), $input['timestamp'] ?? null);
        }
    }

    public function test_candidate_responses_must_correlate_to_the_requested_participant(): void
    {
        config()->set('votetrust-user.services.candidate', [
            'base_url' => 'https://candidate.test',
            'api_key' => 'candidate-consumer-key',
            'connect_timeout_seconds' => 1,
            'timeout_seconds' => 2,
            'response_max_age_seconds' => 300,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request): mixed {
            $input = $this->requestInput($request);

            return Http::response([
                'status' => 'S',
                'message' => 'Candidate data retrieved.',
                'requestID' => $input['requestID'] ?? null,
                'timestamp' => now()->toAtomString(),
                'data' => $request->method() === 'GET'
                    ? ['electionId' => 999, 'candidates' => []]
                    : ['electionId' => 999, 'userId' => 888, 'status' => 'Rejected'],
            ]);
        });

        $client = app(CandidateModuleClient::class);

        self::assertSame([], $client->participants(14));
        self::assertFalse($client->saveParticipant(14, 22, 'Approved'));
        self::assertFalse($client->changeParticipantStatus(14, 22, 'Approved'));
    }

    /** @return array<string, mixed> */
    private function requestInput(ClientRequest $request): array
    {
        if ($request->method() === 'GET') {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $query;
        }

        $payload = json_decode($request->body(), true);

        return is_array($payload) ? $payload : [];
    }
}
