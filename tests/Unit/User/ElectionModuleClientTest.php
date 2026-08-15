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
}
