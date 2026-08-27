<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use App\Services\User\ElectionModuleClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ElectionModuleClientTest extends TestCase
{
    public function test_all_required_election_functions_use_authenticated_rest_requests(): void
    {
        $now = CarbonImmutable::parse('2026-08-15 12:00:00', config('app.timezone'));
        $this->travelTo($now);
        config()->set('votetrust-user.services.election', [
            'base_url' => 'https://election.test',
            'api_key' => 'election-consumer-key',
            'connect_timeout_seconds' => 1,
            'timeout_seconds' => 2,
            'response_max_age_seconds' => 300,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) use ($now): mixed {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $summary = [
                'electionId' => 14,
                'title' => 'REST Boundary Election',
                'lifecycleState' => 'VOTING_OPEN',
                'organizerUserId' => 7,
                'nominationStart' => null,
                'nominationEnd' => null,
                'votingStart' => null,
                'votingEnd' => null,
                'updatedAt' => $now->toAtomString(),
            ];
            $catalog = [
                'electionId' => 14,
                'title' => 'REST Boundary Election',
                'lifecycleState' => 'VOTING_OPEN',
                'votingAllowed' => true,
                'eligibility' => ['type' => 'PUBLIC'],
                'nominationPeriod' => ['start' => null, 'end' => null],
                'votingPeriod' => ['start' => null, 'end' => null],
            ];

            $data = match ($path) {
                '/api/v1/elections/summaries' => ['elections' => [$summary]],
                '/api/v1/elections/voter-catalog' => ['elections' => [$catalog]],
                '/api/v1/elections/organizer/7' => ['elections' => [$summary]],
                '/api/v1/elections/admin/overview' => [
                    'counts' => ['elections' => 1, 'active' => 1, 'pendingApprovals' => 0],
                    'recentElections' => [$summary],
                    'pendingApprovals' => [],
                ],
                '/api/v1/elections/14/availability' => [
                    'electionId' => 14,
                    'eligibility' => ['type' => 'PUBLIC'],
                ],
                default => [],
            };

            return Http::response([
                'status' => 'S',
                'message' => 'Election data retrieved.',
                'requestID' => $query['requestID'] ?? null,
                'timestamp' => $now->toAtomString(),
                'data' => $data,
            ]);
        });

        $client = app(ElectionModuleClient::class);

        self::assertSame(14, $client->summary(14)['id'] ?? null);
        self::assertSame('PUBLIC', $client->voterCatalog()[0]['eligibilityType'] ?? null);
        self::assertSame('PUBLIC', $client->eligibilityMode(14)['type'] ?? null);
        self::assertSame(14, $client->organizedBy(7)[0]['id'] ?? null);
        self::assertSame(1, $client->adminOverview()['counts']['elections'] ?? null);

        Http::assertSentCount(5);
        foreach (Http::recorded() as [$request]) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            self::assertSame('GET', $request->method());
            self::assertTrue($request->hasHeader('X-Internal-Api-Key', 'election-consumer-key'));
            self::assertMatchesRegularExpression('/\Ausr_[a-f0-9]{32}\z/', (string) ($query['requestID'] ?? ''));
            self::assertSame($now->toAtomString(), $query['timestamp'] ?? null);
        }
    }

    public function test_summaries_preserve_valid_schedule_timestamps_and_null_invalid_values(): void
    {
        $now = CarbonImmutable::parse('2026-08-15 12:00:00', config('app.timezone'));
        $this->travelTo($now);
        config()->set('votetrust-user.services.election', [
            'base_url' => 'https://election.test',
            'api_key' => 'election-consumer-key',
            'connect_timeout_seconds' => 1,
            'timeout_seconds' => 2,
            'response_max_age_seconds' => 300,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) use ($now) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response([
                'status' => 'S',
                'message' => 'Election summaries retrieved.',
                'requestID' => $query['requestID'] ?? null,
                'timestamp' => $now->toAtomString(),
                'data' => ['elections' => [
                    [
                        'electionId' => 41,
                        'title' => 'Valid Schedule Election',
                        'lifecycleState' => 'NOMINATION_OPEN',
                        'organizerUserId' => 7,
                        'nominationStart' => '2026-08-16T09:15:00+08:00',
                        'nominationEnd' => '2026-08-18T17:30:00+08:00',
                        'votingStart' => '2026-08-19T09:00:00+08:00',
                        'votingEnd' => '2026-08-20T17:30:00+08:00',
                        'updatedAt' => '2026-08-15T10:00:00+08:00',
                    ],
                    [
                        'electionId' => 42,
                        'title' => 'Malformed Schedule Election',
                        'lifecycleState' => 'VOTING_OPEN',
                        'organizerUserId' => null,
                        'nominationStart' => 'not-a-timestamp',
                        'nominationEnd' => '   ',
                        'votingStart' => 1234,
                        'votingEnd' => 'tomorrow',
                        'updatedAt' => null,
                    ],
                ]],
            ]);
        });

        $summaries = app(ElectionModuleClient::class)->summaries([41, 42]);

        self::assertSame('2026-08-16T09:15:00+08:00', $summaries[41]['nominationStart']);
        self::assertSame('2026-08-18T17:30:00+08:00', $summaries[41]['nominationEnd']);
        self::assertSame('2026-08-19T09:00:00+08:00', $summaries[41]['votingStart']);
        self::assertSame('2026-08-20T17:30:00+08:00', $summaries[41]['votingEnd']);
        self::assertArrayHasKey(42, $summaries);
        self::assertNull($summaries[42]['nominationStart']);
        self::assertNull($summaries[42]['nominationEnd']);
        self::assertNull($summaries[42]['votingStart']);
        self::assertNull($summaries[42]['votingEnd']);
    }

    public function test_availability_rejects_an_election_id_from_another_request(): void
    {
        config()->set('votetrust-user.services.election', [
            'base_url' => 'https://election.test',
            'api_key' => 'election-consumer-key',
            'connect_timeout_seconds' => 1,
            'timeout_seconds' => 2,
            'response_max_age_seconds' => 300,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request): mixed {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response([
                'status' => 'S',
                'message' => 'Election availability retrieved.',
                'requestID' => $query['requestID'] ?? null,
                'timestamp' => now()->toAtomString(),
                'data' => [
                    'electionId' => 999,
                    'eligibility' => ['type' => 'PUBLIC'],
                ],
            ]);
        });

        self::assertNull(app(ElectionModuleClient::class)->eligibilityMode(14));
    }
}
