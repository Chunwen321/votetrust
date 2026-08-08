<?php

namespace Tests\Feature;

use App\Contracts\VotingIntegration;
use App\Models\Candidacy;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoterEnrollment;
use App\Services\Voting\IntegrationException;
use App\Services\Voting\VoteAlreadyExists;
use App\Services\Voting\VoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// PIC: Goh Chun Wen - Voting Process Decorator and REST integration tests.
class VotingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'voting.api_key' => 'voting-provider-key',
            'voting.services.election.base_url' => 'https://election.test',
            'voting.services.election.api_key' => 'election-consumer-key',
            'voting.services.user.base_url' => 'https://user.test',
            'voting.services.user.api_key' => 'user-consumer-key',
        ]);
    }

    public function test_decorated_service_consumes_teammate_apis_and_allows_exactly_one_vote(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        $this->fakeTeammateApis($voter, $candidate, $election);
        $service = $this->app->make(VoteService::class);
        $timestamp = now()->toAtomString();

        $vote = $service->cast(
            $voter,
            $election->id,
            $candidate->id,
            'VOTE-TEST-0001',
            $timestamp,
        );

        $this->assertDatabaseHas('votes', [
            'id' => $vote->id,
            'voter_id' => $voter->id,
            'candidate_id' => $candidate->id,
            'election_id' => $election->id,
        ]);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://user.test/api/v1/voters/eligibility')
            && $request->hasHeader('X-Internal-Api-Key', 'user-consumer-key')
            && $request['voterId'] === $voter->id
            && $request['electionId'] === $election->id
            && $request['requestID'] === 'VOTE-TEST-0001'
            && $request['timestamp'] === $timestamp);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), "https://election.test/api/v1/elections/{$election->id}/availability")
            && $request->hasHeader('X-Internal-Api-Key', 'election-consumer-key')
            && $request['requestID'] === 'VOTE-TEST-0001'
            && $request['timestamp'] === $timestamp);

        $this->expectException(VoteAlreadyExists::class);
        $service->cast(
            $voter,
            $election->id,
            $candidate->id,
            'VOTE-TEST-0002',
            now()->toAtomString(),
        );
    }

    public function test_http_consumer_fails_closed_on_a_stale_ifa_response(): void
    {
        Http::fake([
            'https://user.test/*' => Http::response([
                'status' => 'S',
                'requestID' => 'VOTE-TEST-STALE',
                'timestamp' => now()->subHour()->toAtomString(),
                'data' => ['voterId' => 1, 'electionId' => 2, 'eligible' => true],
            ]),
        ]);

        $this->expectException(IntegrationException::class);

        $this->app->make(VotingIntegration::class)->voterIsEligible(
            1,
            2,
            'VOTE-TEST-STALE',
            now()->toAtomString(),
        );
    }

    public function test_voting_status_provider_returns_the_required_ifa_envelope_without_ballot_choice(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        Vote::query()->create([
            'voter_id' => $voter->id,
            'candidate_id' => $candidate->id,
            'election_id' => $election->id,
            'cast_at' => now(),
        ]);

        $response = $this->withHeader('X-Internal-Api-Key', 'voting-provider-key')
            ->getJson('/api/v1/votes/status?'.http_build_query([
                'voterId' => $voter->id,
                'electionId' => $election->id,
                'requestID' => 'STATUS-TEST-0001',
                'timestamp' => now()->toAtomString(),
            ]));

        $response->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'STATUS-TEST-0001')
            ->assertJsonPath('data.hasVoted', true)
            ->assertJsonMissingPath('data.candidateId');

        $this->assertIsString($response->json('timestamp'));
    }

    private function fakeTeammateApis(User $voter, User $candidate, Election $election): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($voter, $candidate, $election) {
            $requestId = (string) $request['requestID'];

            if (str_starts_with($request->url(), 'https://user.test/api/v1/voters/eligibility')) {
                return Http::response([
                    'status' => 'S',
                    'message' => 'Voter eligibility retrieved.',
                    'requestID' => $requestId,
                    'timestamp' => now()->toAtomString(),
                    'data' => [
                        'voterId' => $voter->id,
                        'electionId' => $election->id,
                        'eligible' => true,
                    ],
                ]);
            }

            if (str_starts_with($request->url(), "https://election.test/api/v1/elections/{$election->id}/availability")) {
                return Http::response([
                    'status' => 'S',
                    'message' => 'Election availability retrieved.',
                    'requestID' => $requestId,
                    'timestamp' => now()->toAtomString(),
                    'data' => [
                        'electionId' => $election->id,
                        'title' => $election->title,
                        'description' => $election->description ?? '',
                        'lifecycleState' => 'VOTING_OPEN',
                        'eligibilityRules' => ['mode' => 'REGISTERED_VOTERS'],
                        'votingAllowed' => true,
                        'votingPeriod' => [
                            'start' => $election->voting_start?->toAtomString(),
                            'end' => $election->voting_end?->toAtomString(),
                        ],
                        'ballot' => [[
                            'id' => 1,
                            'candidateId' => $candidate->id,
                            'name' => $candidate->display_name,
                            'position' => 'President',
                            'manifesto' => 'A trustworthy ballot.',
                        ]],
                    ],
                ]);
            }

            return Http::response(['status' => 'E', 'timestamp' => now()->toAtomString()], 404);
        });
    }

    /** @return array{User, User, Election} */
    private function ballotFixture(): array
    {
        $voter = User::factory()->create();
        $candidate = User::factory()->create();
        Candidate::query()->create(['user_id' => $candidate->id]);
        $election = Election::query()->create([
            'title' => 'Open election',
            'status' => Election::VOTING_OPEN,
            'voting_start' => now()->subMinute(),
            'voting_end' => now()->addHour(),
        ]);
        Candidacy::query()->create([
            'candidate_id' => $candidate->id,
            'election_id' => $election->id,
            'status' => Candidacy::APPROVED,
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $voter->id,
            'election_id' => $election->id,
            'status' => VoterEnrollment::APPROVED,
        ]);

        return [$voter, $candidate, $election];
    }
}
