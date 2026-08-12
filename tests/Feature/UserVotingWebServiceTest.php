<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Election;
use App\Models\User;
use App\Models\VoterEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class UserVotingWebServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_dashboard_consumes_voting_status_using_the_ifa_contract(): void
    {
        [$voter, $election] = $this->enrolledVoter();
        $this->configureVotingService();
        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) use ($voter, $election) {
            $query = $this->queryFrom($request);

            return Http::response([
                'status' => 'S',
                'message' => 'Voting status retrieved.',
                'requestID' => $query['requestID'] ?? null,
                'timestamp' => now()->toAtomString(),
                'data' => [
                    'voterId' => $voter->getKey(),
                    'electionId' => $election->getKey(),
                    'hasVoted' => true,
                ],
            ]);
        });

        $this->actingAs($voter)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your election space')
            ->assertSee('Everything important, without the clutter.');

        Http::assertSent(function (ClientRequest $request) use ($voter, $election): bool {
            $query = $this->queryFrom($request);

            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://voting.test/api/v1/votes/status?')
                && $request->hasHeader('X-Internal-Api-Key', 'voting-consumer-key')
                && ($query['voterId'] ?? null) === (string) $voter->getKey()
                && ($query['electionId'] ?? null) === (string) $election->getKey()
                && preg_match('/\Ausr_[a-f0-9]{32}\z/', (string) ($query['requestID'] ?? '')) === 1
                && strtotime((string) ($query['timestamp'] ?? '')) !== false;
        });
    }

    public function test_a_mismatched_voting_response_fails_closed_on_the_voter_dashboard(): void
    {
        [$voter] = $this->enrolledVoter();
        $this->configureVotingService();
        Http::preventStrayRequests();
        Http::fake([
            'https://voting.test/api/v1/votes/status*' => Http::response([
                'status' => 'S',
                'message' => 'Voting status retrieved.',
                'requestID' => 'response_for_another_request',
                'timestamp' => now()->toAtomString(),
                'data' => [
                    'voterId' => $voter->getKey(),
                    'electionId' => 999,
                    'hasVoted' => false,
                ],
            ]),
        ]);

        $this->actingAs($voter)
            ->get(route('voter.dashboard'))
            ->assertOk()
            ->assertSee('Unavailable')
            ->assertDontSee('Not voted');

    }

    /** @return array{User, Election} */
    private function enrolledVoter(): array
    {
        $voter = User::factory()->create([
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $election = Election::query()->create([
            'title' => 'Web Service Election',
            'status' => Election::VOTING_OPEN,
            'currency' => 'MYR',
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $voter->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ]);

        return [$voter, $election];
    }

    private function configureVotingService(): void
    {
        config()->set('votetrust-user.services.voting', [
            'base_url' => 'https://voting.test',
            'api_key' => 'voting-consumer-key',
            'connect_timeout_seconds' => 1,
            'timeout_seconds' => 2,
            'response_max_age_seconds' => 300,
        ]);
    }

    /** @return array<string, string> */
    private function queryFrom(ClientRequest $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return array_map('strval', $query);
    }
}
