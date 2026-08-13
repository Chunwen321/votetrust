<?php

namespace Tests\Feature;

use App\Contracts\VotingIntegration;
use App\Models\Candidacy;
use App\Models\Election;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoterEnrollment;
use App\Services\Voting\IntegrationException;
use App\Services\Voting\InvalidVoteRequest;
use App\Services\Voting\VoteAccessDenied;
use App\Services\Voting\VoteAlreadyExists;
use App\Services\Voting\VoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
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

        $this->assertDatabaseHas('vote', [
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

    public function test_service_validation_decorator_rejects_invalid_input_before_any_api_call(): void
    {
        [$voter, $candidate] = $this->ballotFixture();
        Http::preventStrayRequests();

        try {
            $this->app->make(VoteService::class)->cast(
                $voter,
                0,
                $candidate->id,
                'invalid request id',
                'not-a-timestamp',
            );
            self::fail('An invalid vote command was accepted.');
        } catch (InvalidVoteRequest $exception) {
            self::assertSame('The vote request contains an invalid identifier.', $exception->getMessage());
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('vote', 0);
    }

    public function test_http_consumer_fails_closed_when_a_service_api_key_is_missing(): void
    {
        config(['voting.services.election.api_key' => '']);
        Http::preventStrayRequests();

        try {
            $this->app->make(VotingIntegration::class)->openElections(
                'VOTE-NO-KEY-0001',
                now()->toAtomString(),
            );
            self::fail('The unsecured Election API request was sent.');
        } catch (IntegrationException $exception) {
            self::assertSame('The election web service is not configured.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_a_locked_vote_submission_is_rejected_without_creating_a_second_vote(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        $this->fakeTeammateApis($voter, $candidate, $election);
        config(['voting.lock_wait_seconds' => 0]);
        $lock = Cache::lock("voting:cast:{$voter->id}:{$election->id}", 10);
        self::assertTrue($lock->get());

        try {
            $this->app->make(VoteService::class)->cast(
                $voter,
                $election->id,
                $candidate->id,
                'VOTE-LOCK-0001',
                now()->toAtomString(),
            );
            self::fail('A simultaneous vote submission acquired an occupied voter/election lock.');
        } catch (VoteAlreadyExists $exception) {
            self::assertSame(
                'A vote submission is already being processed for this election.',
                $exception->getMessage(),
            );
        } finally {
            $lock->release();
        }

        $this->assertDatabaseCount('vote', 0);
    }

    public function test_underage_voter_is_rejected(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        $this->fakeTeammateApis(
            $voter,
            $candidate,
            $election,
            $this->eligibilityRules(18),
            ['dateOfBirth' => now()->subYears(18)->addDay()->toDateString(), 'nationality' => ['countryCode' => 'MY']],
        );

        $this->expectException(VoteAccessDenied::class);
        $this->expectExceptionMessage('minimum age');
        $this->app->make(VoteService::class)->cast($voter, $election->id, $candidate->id, 'VOTE-AGE-0001', now()->toAtomString());
    }

    public function test_wrong_nationality_is_rejected(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        $this->fakeTeammateApis(
            $voter,
            $candidate,
            $election,
            $this->eligibilityRules(null, 'MY'),
            ['dateOfBirth' => null, 'nationality' => ['countryCode' => 'SG']],
        );

        $this->expectException(VoteAccessDenied::class);
        $this->expectExceptionMessage('nationality requirement');
        $this->app->make(VoteService::class)->cast($voter, $election->id, $candidate->id, 'VOTE-NATION-0001', now()->toAtomString());
    }

    public function test_any_nationality_accepts_an_adult_voter_from_another_country(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        $this->fakeTeammateApis(
            $voter,
            $candidate,
            $election,
            $this->eligibilityRules(18),
            ['dateOfBirth' => '1990-01-01', 'nationality' => ['countryCode' => 'SG']],
        );

        $vote = $this->app->make(VoteService::class)->cast(
            $voter,
            $election->id,
            $candidate->id,
            'VOTE-ANY-NATION-0001',
            now()->toAtomString(),
        );

        self::assertSame($voter->id, $vote->voter_id);
    }

    public function test_public_election_does_not_require_demographic_identity_data(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        $this->fakeTeammateApis(
            $voter,
            $candidate,
            $election,
            $this->eligibilityRules(null, null, 'PUBLIC'),
            ['dateOfBirth' => null, 'nationality' => ['countryCode' => null]],
        );

        $vote = $this->app->make(VoteService::class)->cast(
            $voter,
            $election->id,
            $candidate->id,
            'VOTE-PUBLIC-0001',
            now()->toAtomString(),
        );

        self::assertSame($voter->id, $vote->voter_id);
    }

    public function test_missing_required_identity_information_fails_closed(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        $this->fakeTeammateApis(
            $voter,
            $candidate,
            $election,
            $this->eligibilityRules(18, 'MY'),
            ['dateOfBirth' => null, 'nationality' => ['countryCode' => null]],
        );

        $this->expectException(VoteAccessDenied::class);
        $this->expectExceptionMessage('date of birth is unavailable');
        $this->app->make(VoteService::class)->cast($voter, $election->id, $candidate->id, 'VOTE-MISSING-0001', now()->toAtomString());
    }

    public function test_missing_required_nationality_fails_closed(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        $this->fakeTeammateApis(
            $voter,
            $candidate,
            $election,
            $this->eligibilityRules(null, 'MY'),
            ['dateOfBirth' => null, 'nationality' => ['countryCode' => null]],
        );

        $this->expectException(VoteAccessDenied::class);
        $this->expectExceptionMessage('nationality is unavailable');
        $this->app->make(VoteService::class)->cast($voter, $election->id, $candidate->id, 'VOTE-MISSING-NATION-0001', now()->toAtomString());
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

    public function test_tally_provider_releases_counts_only_after_the_election_is_closed(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        Vote::query()->create([
            'voter_id' => $voter->id,
            'candidate_id' => $candidate->id,
            'election_id' => $election->id,
            'cast_at' => now(),
        ]);
        Http::preventStrayRequests();
        Http::fake([
            "https://election.test/api/v1/elections/{$election->id}/availability*" => Http::response([
                'status' => 'S',
                'message' => 'Election availability retrieved.',
                'requestID' => 'TALLY-TEST-0001',
                'timestamp' => now()->toAtomString(),
                'data' => [
                    'electionId' => $election->id,
                    'title' => $election->title,
                    'lifecycleState' => 'CLOSED',
                    'eligibility' => $this->eligibilityRules(null),
                    'votingAllowed' => false,
                    'votingPeriod' => ['start' => null, 'end' => null],
                    'ballot' => [[
                        'id' => 1,
                        'candidateId' => $candidate->id,
                        'name' => $candidate->display_name,
                    ]],
                ],
            ]),
        ]);

        $response = $this->withHeader('X-Internal-Api-Key', 'voting-provider-key')
            ->getJson("/api/v1/elections/{$election->id}/tally?".http_build_query([
                'requestID' => 'TALLY-TEST-0001',
                'timestamp' => now()->toAtomString(),
            ]));

        $response->assertOk()
            ->assertJsonPath('data.electionId', $election->id)
            ->assertJsonPath('data.tally.0.candidateId', $candidate->id)
            ->assertJsonPath('data.tally.0.votes', 1);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader(
            'X-Internal-Api-Key',
            'election-consumer-key',
        ));
    }

    public function test_tally_provider_does_not_release_counts_before_the_election_closes(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        Vote::query()->create([
            'voter_id' => $voter->id,
            'candidate_id' => $candidate->id,
            'election_id' => $election->id,
            'cast_at' => now(),
        ]);
        $this->fakeTeammateApis($voter, $candidate, $election);

        $this->withHeader('X-Internal-Api-Key', 'voting-provider-key')
            ->getJson("/api/v1/elections/{$election->id}/tally?".http_build_query([
                'requestID' => 'TALLY-OPEN-0001',
                'timestamp' => now()->toAtomString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Votes can only be tallied after the election closes.')
            ->assertJsonMissingPath('data.tally');
    }

    public function test_voting_api_rejects_unauthenticated_and_malformed_requests_before_controller_logic(): void
    {
        $query = http_build_query([
            'voterId' => 1,
            'electionId' => 1,
            'requestID' => 'STATUS-SECURITY-0001',
            'timestamp' => now()->toAtomString(),
        ]);

        $this->getJson('/api/v1/votes/status?'.$query)
            ->assertForbidden()
            ->assertJsonPath('message', 'Voting API access denied.');

        $this->withHeader('X-Internal-Api-Key', 'voting-provider-key')
            ->getJson('/api/v1/votes/status?'.http_build_query([
                'voterId' => 1,
                'electionId' => 1,
                'requestID' => 'bad id',
                'timestamp' => 'not-a-timestamp',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The Voting API request is invalid.')
            ->assertJsonStructure(['errors' => ['requestID', 'timestamp']]);
    }

    public function test_vote_receipts_are_owner_scoped_and_do_not_reveal_other_voters_records(): void
    {
        [$owner, $candidate, $election] = $this->ballotFixture();
        $intruder = User::factory()->create();
        $vote = Vote::query()->create([
            'voter_id' => $owner->id,
            'candidate_id' => $candidate->id,
            'election_id' => $election->id,
            'cast_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->get(route('voting.receipt', $vote->id))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('voting.receipt', $vote->id))
            ->assertOk()
            ->assertSee('VT-'.str_pad((string) $vote->id, 8, '0', STR_PAD_LEFT))
            ->assertDontSee($candidate->email);
    }

    public function test_voting_pages_require_an_authenticated_verified_account(): void
    {
        $this->get(route('voting.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverifiedProfile()->create())
            ->get(route('voting.index'))
            ->assertRedirect(route('profile.verify'));
    }

    public function test_cast_route_rejects_invalid_ballot_fields_without_sending_api_requests(): void
    {
        [$voter, $candidate, $election] = $this->ballotFixture();
        Http::preventStrayRequests();

        $this->actingAs($voter)
            ->from(route('voting.ballot', $election->id))
            ->post(route('voting.cast', $election->id), [
                'candidate_id' => $candidate->id,
                'request_id' => ['not-a-string'],
                'timestamp' => 'not-a-timestamp',
            ])
            ->assertRedirect(route('voting.ballot', $election->id))
            ->assertSessionHasErrors(['request_id', 'timestamp']);

        Http::assertNothingSent();
        $this->assertDatabaseCount('vote', 0);
    }

    /** @param array<string, mixed>|null $eligibility
     * @param  array<string, mixed>|null  $identity
     */
    private function fakeTeammateApis(
        User $voter,
        User $candidate,
        Election $election,
        ?array $eligibility = null,
        ?array $identity = null,
    ): void {
        $eligibility ??= $this->eligibilityRules(18, 'MY');
        $identity ??= [
            'dateOfBirth' => $voter->date_of_birth?->toDateString(),
            'nationality' => ['countryCode' => $voter->nationality_country_code],
        ];
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($voter, $candidate, $election, $eligibility, $identity) {
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
                        'reasonCode' => 'APPROVED',
                        'identity' => $identity,
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
                        'eligibility' => $eligibility,
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

    /** @return array<string, mixed> */
    private function eligibilityRules(?int $minimumAge, ?string $countryCode = null, string $type = 'PUBLIC'): array
    {
        return [
            'type' => $type,
            'minimumAge' => $type === 'PUBLIC' ? $minimumAge : null,
            'nationality' => [
                'restricted' => $type === 'PUBLIC' && $countryCode !== null,
                'countryCode' => $type === 'PUBLIC' ? $countryCode : null,
                'countryName' => $countryCode === 'MY' ? 'Malaysia' : ($countryCode === 'SG' ? 'Singapore' : null),
            ],
        ];
    }

    /** @return array{User, User, Election} */
    private function ballotFixture(): array
    {
        $voter = User::factory()->create();
        $candidate = User::factory()->create();
        $election = Election::query()->create([
            'title' => 'Open election',
            'status' => Election::VOTING_OPEN,
            'voting_start' => now()->subMinute(),
            'voting_end' => now()->addHour(),
        ]);
        Candidacy::query()->create([
            'user_id' => $candidate->id,
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
