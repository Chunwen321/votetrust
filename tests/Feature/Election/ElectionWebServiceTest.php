<?php

// PIC: Low Wen Kai

namespace Tests\Feature\Election;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\States\ElectionStatus;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ElectionWebServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('election.api.key', 'election-provider-key');
    }

    public function test_available_and_detail_endpoints_publish_voting_data_in_ifa_envelopes(): void
    {
        $now = CarbonImmutable::now();
        [$election, $approvedCandidacy] = $this->votingElection($now);

        $list = $this->withHeader('X-Internal-Api-Key', 'election-provider-key')
            ->getJson('/api/v1/elections/available?'.http_build_query([
                'requestID' => 'VOTE-LIST-0001',
                'timestamp' => $now->toAtomString(),
            ]));

        $list->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'VOTE-LIST-0001')
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.elections.0.electionId', $election->getKey())
            ->assertJsonPath('data.elections.0.title', 'Open Student Election')
            ->assertJsonPath('data.elections.0.positionSought', 'President')
            ->assertJsonPath('data.elections.0.eligibility.type', EligibilityRules::PUBLIC)
            ->assertJsonPath('data.elections.0.eligibility.minimumAge', 18)
            ->assertJsonPath('data.elections.0.eligibility.nationality.countryCode', 'MY')
            ->assertJsonPath('data.elections.0.ballotCandidateCount', 1)
            ->assertJsonStructure(['status', 'timestamp', 'data' => ['elections']]);

        $detail = $this->withHeader('X-Internal-Api-Key', 'election-provider-key')
            ->getJson('/api/v1/elections/'.$election->getKey().'/availability?'.http_build_query([
                'requestID' => 'VOTE-DETAIL-0001',
                'timestamp' => $now->toAtomString(),
            ]));

        $detail->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'VOTE-DETAIL-0001')
            ->assertJsonPath('data.electionId', $election->getKey())
            ->assertJsonPath('data.positionSought', 'President')
            ->assertJsonPath('data.description', 'The election API supplies an approved ballot to the voting consumer.')
            ->assertJsonPath('data.eligibility.type', EligibilityRules::PUBLIC)
            ->assertJsonPath('data.eligibility.minimumAge', 18)
            ->assertJsonPath('data.eligibility.nationality.restricted', true)
            ->assertJsonPath('data.eligibility.nationality.countryCode', 'MY')
            ->assertJsonPath('data.eligibility.nationality.countryName', 'Malaysia')
            ->assertJsonMissingPath('data.eligibility.occupation')
            ->assertJsonPath('data.votingAllowed', true)
            ->assertJsonPath('data.ballot.0.id', $approvedCandidacy->getKey())
            ->assertJsonPath('data.ballot.0.candidateId', $approvedCandidacy->user_id)
            ->assertJsonPath('data.ballot.0.name', 'Approved Candidate')
            ->assertJsonPath('data.ballot.0.position', 'President')
            ->assertJsonPath('data.ballot.0.manifesto', 'A transparent manifesto supplied through the Election REST provider.')
            ->assertJsonPath('data.ballot.0.introduction', 'An experienced student representative focused on accountable leadership.')
            ->assertJsonPath('data.ballot.0.qualifications', 'Two years of student council and community project experience.')
            ->assertJsonPath('data.ballot.0.reasonForCandidacy', 'To represent students clearly and publish measurable progress.')
            ->assertJsonPath('data.ballot.0.photoUrl', null)
            ->assertJsonCount(1, 'data.ballot')
            ->assertJsonStructure(['status', 'timestamp', 'data' => ['ballot']]);
    }

    public function test_voter_catalog_includes_published_nomination_and_voting_stages_but_excludes_unpublished_elections(): void
    {
        $now = CarbonImmutable::parse('2026-08-21 12:00:00', config('app.timezone'));
        $this->travelTo($now);
        $admin = User::factory()->create(['is_admin' => true]);

        $ended = $this->catalogElection(
            'Ended Awaiting Closure',
            ElectionStatus::VOTING_OPEN,
            $now->subDays(2),
            $now->subMinutes(5),
            EligibilityRules::publicElection(),
            $admin,
        );
        $active = $this->catalogElection(
            'Active Public Election',
            ElectionStatus::VOTING_OPEN,
            $now->subHour(),
            $now->addHour(),
            EligibilityRules::publicElection(18, 'MY'),
            $admin,
        );
        $nominating = $this->catalogElection(
            'Public Nominations Open',
            ElectionStatus::NOMINATION_OPEN,
            $now->addDays(2),
            $now->addDays(3),
            EligibilityRules::publicElection(16),
            $admin,
        );
        $nominationsClosed = $this->catalogElection(
            'Public Nominations Closed',
            ElectionStatus::NOMINATION_CLOSED,
            $now->addDay(),
            $now->addDays(2),
            EligibilityRules::publicElection(),
            $admin,
        );
        $closed = $this->catalogElection(
            'Closed Public Election',
            ElectionStatus::CLOSED,
            $now->subDays(3),
            $now->subHour(),
            EligibilityRules::publicElection(),
            $admin,
        );
        $private = $this->catalogElection(
            'Closed Private Election',
            ElectionStatus::CLOSED,
            $now->subDays(4),
            $now->subHours(2),
            EligibilityRules::privateElection(),
            $admin,
        );
        $draft = $this->catalogElection(
            'Unpublished Draft Election',
            ElectionStatus::DRAFT,
            $now->addDays(2),
            $now->addDays(3),
            EligibilityRules::publicElection(),
            $admin,
        );

        $response = $this->withHeader('X-Internal-Api-Key', 'election-provider-key')
            ->getJson('/api/v1/elections/voter-catalog?'.http_build_query([
                'requestID' => 'VOTER-CATALOG-0001',
                'timestamp' => $now->toAtomString(),
            ]));

        $response->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'VOTER-CATALOG-0001')
            ->assertJsonPath('data.count', 6)
            ->assertJsonMissing(['electionId' => $draft->getKey()]);

        $catalog = collect($response->json('data.elections'));
        self::assertSame(
            [
                'Ended Awaiting Closure',
                'Active Public Election',
                'Public Nominations Closed',
                'Public Nominations Open',
                'Closed Public Election',
                'Closed Private Election',
            ],
            $catalog->pluck('title')->all(),
        );

        $byId = $catalog->keyBy('electionId');
        self::assertTrue($byId[$active->getKey()]['votingAllowed']);
        self::assertFalse($byId[$nominationsClosed->getKey()]['votingAllowed']);
        self::assertFalse($byId[$nominating->getKey()]['votingAllowed']);
        self::assertSame(
            $nominating->nomination_start?->toAtomString(),
            $byId[$nominating->getKey()]['nominationPeriod']['start'],
        );
        self::assertSame(
            $nominating->nomination_end?->toAtomString(),
            $byId[$nominating->getKey()]['nominationPeriod']['end'],
        );
        self::assertFalse($byId[$ended->getKey()]['votingAllowed']);
        self::assertFalse($byId[$closed->getKey()]['votingAllowed']);
        self::assertSame(EligibilityRules::PUBLIC, $byId[$closed->getKey()]['eligibility']['type']);
        self::assertSame(EligibilityRules::PRIVATE, $byId[$private->getKey()]['eligibility']['type']);
        self::assertArrayNotHasKey('privateVoters', $byId[$private->getKey()]);
        self::assertArrayNotHasKey('voterIds', $byId[$private->getKey()]);
    }

    public function test_summary_endpoint_publishes_the_complete_election_schedule(): void
    {
        config()->set('election.api.key', 'election-summary-contract-key');
        $now = CarbonImmutable::parse('2026-08-15 12:00:00', config('app.timezone'));
        $this->travelTo($now);

        $election = Election::query()->create([
            'title' => 'Scheduled Student Election',
            'nomination_start' => $now->addDay()->setTime(9, 15),
            'nomination_end' => $now->addDays(3)->setTime(17, 30),
            'voting_start' => $now->addDays(4)->setTime(9, 0),
            'voting_end' => $now->addDays(5)->setTime(17, 30),
            'status' => Election::NOMINATION_OPEN,
            'currency' => 'MYR',
        ])->refresh();

        $this->withHeader('X-Internal-Api-Key', 'election-summary-contract-key')
            ->getJson(route('api.elections.summaries').'?'.http_build_query([
                'electionIds' => [$election->getKey()],
                'requestID' => 'ELECTION-SUMMARY-SCHEDULE-0001',
                'timestamp' => $now->toAtomString(),
            ]))
            ->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'ELECTION-SUMMARY-SCHEDULE-0001')
            ->assertJsonPath('data.elections.0.electionId', $election->getKey())
            ->assertJsonPath('data.elections.0.nominationStart', $election->nomination_start?->toAtomString())
            ->assertJsonPath('data.elections.0.nominationEnd', $election->nomination_end?->toAtomString())
            ->assertJsonPath('data.elections.0.votingStart', $election->voting_start?->toAtomString())
            ->assertJsonPath('data.elections.0.votingEnd', $election->voting_end?->toAtomString());
    }

    public function test_provider_endpoints_return_failure_envelopes_for_invalid_requests(): void
    {
        $response = $this->withHeader('X-Internal-Api-Key', 'election-provider-key')
            ->getJson('/api/v1/elections/available?requestID=BAD');

        $response->assertUnprocessable()
            ->assertJsonPath('status', 'F')
            ->assertJsonPath('requestID', 'BAD')
            ->assertJsonStructure(['status', 'timestamp', 'errors']);
    }

    public function test_voting_catalog_and_detail_endpoints_require_the_internal_api_key(): void
    {
        $now = CarbonImmutable::now();
        [$election] = $this->votingElection($now);
        $catalogUrl = '/api/v1/elections/available?'.http_build_query([
            'requestID' => 'VOTE-CATALOG-AUTH-0001',
            'timestamp' => $now->toAtomString(),
        ]);
        $detailUrl = '/api/v1/elections/'.$election->getKey().'/availability?'.http_build_query([
            'requestID' => 'VOTE-DETAIL-AUTH-0001',
            'timestamp' => $now->toAtomString(),
        ]);
        $voterCatalogUrl = '/api/v1/elections/voter-catalog?'.http_build_query([
            'requestID' => 'VOTER-CATALOG-AUTH-0001',
            'timestamp' => $now->toAtomString(),
        ]);

        $this->getJson($catalogUrl)
            ->assertForbidden()
            ->assertJsonPath('status', 'F')
            ->assertJsonPath('message', 'Election API access denied.')
            ->assertJsonMissingPath('data');

        $this->withHeader('X-Internal-Api-Key', 'wrong-election-provider-key')
            ->getJson($detailUrl)
            ->assertForbidden()
            ->assertJsonPath('status', 'F')
            ->assertJsonPath('message', 'Election API access denied.')
            ->assertJsonMissingPath('data');

        config()->set('election.api.key', '');

        $this->withHeader('X-Internal-Api-Key', 'election-provider-key')
            ->getJson($voterCatalogUrl)
            ->assertForbidden()
            ->assertJsonPath('status', 'F')
            ->assertJsonPath('message', 'Election API access denied.')
            ->assertJsonMissingPath('data');
    }

    public function test_admin_approval_endpoint_returns_an_ifa_response(): void
    {
        config()->set('election.admin_api_key', 'admin-contract-secret');
        $now = CarbonImmutable::now();
        $organizer = User::query()->create([
            'name' => 'Election Organizer',
            'email' => 'approval-organizer@example.test',
            'password' => Hash::make('Organizer!Pass1'),
        ]);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $admin = User::query()->create([
            'name' => 'Independent Administrator',
            'email' => 'approval-admin@example.test',
            'password' => Hash::make('Admin!Pass1'),
            'is_admin' => true,
        ]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Approval API Election',
            'position_sought' => 'President',
            'description' => 'Election pending a remote administrator decision.',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => $now->subHour(),
            'nomination_end' => $now->addHour(),
            'voting_start' => $now->addHours(2),
            'voting_end' => $now->addHours(3),
            'hosting_fee' => 100,
            'currency' => 'MYR',
            'status' => ElectionStatus::PENDING_ADMIN_APPROVAL->value,
            'payment_reference' => 'VT-HOST-PAID-002',
            'payment_verified_at' => $now,
            'version' => 1,
            'transition_history_json' => [],
        ]);

        $response = $this->withHeader('X-Admin-API-Key', 'admin-contract-secret')
            ->postJson('/api/v1/elections/'.$election->getKey().'/approval', [
                'requestID' => 'ADMIN-DECISION-0001',
                'timestamp' => $now->toAtomString(),
                'decision' => 'APPROVE',
                'adminUserID' => $admin->getKey(),
                'version' => 1,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('requestID', 'ADMIN-DECISION-0001')
            ->assertJsonPath('data.lifecycleState', ElectionStatus::SCHEDULED->value)
            ->assertJsonStructure(['status', 'timestamp', 'data']);
        self::assertSame(ElectionStatus::SCHEDULED->value, $election->refresh()->status);
    }

    /** @return array{Election,Candidacy} */
    private function votingElection(CarbonImmutable $now): array
    {
        $organizer = User::query()->create([
            'name' => 'Voting Organizer',
            'email' => 'voting-organizer@example.test',
            'password' => Hash::make('Organizer!Pass1'),
        ]);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $admin = User::query()->create([
            'name' => 'Voting Administrator',
            'email' => 'voting-admin@example.test',
            'password' => Hash::make('Admin!Pass1'),
            'is_admin' => true,
        ]);
        $election = Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => 'Open Student Election',
            'position_sought' => 'President',
            'description' => 'The election API supplies an approved ballot to the voting consumer.',
            'eligibility_rules' => EligibilityRules::publicElection(18, 'MY')->toStorage(),
            'nomination_start' => $now->subDays(2),
            'nomination_end' => $now->subDay(),
            'voting_start' => $now->subMinute(),
            'voting_end' => $now->addHour(),
            'hosting_fee' => 100,
            'currency' => 'MYR',
            'status' => ElectionStatus::VOTING_OPEN->value,
            'payment_reference' => 'VT-HOST-PAID-003',
            'payment_verified_at' => $now->subDay(),
            'approved_by' => $admin->getKey(),
            'approved_at' => $now->subDay(),
            'version' => 5,
        ]);
        $candidateUser = User::query()->create([
            'name' => 'Approved Candidate Account',
            'full_name' => 'Approved Candidate',
            'email' => 'approved-candidate@example.test',
            'password' => Hash::make('Candidate!Pass1'),
        ]);
        $approved = Candidacy::query()->create([
            'user_id' => $candidateUser->getKey(),
            'election_id' => $election->getKey(),
            'status' => Candidacy::APPROVED,
            'manifesto' => 'A transparent manifesto supplied through the Election REST provider.',
            'candidate_introduction' => 'An experienced student representative focused on accountable leadership.',
            'qualifications' => 'Two years of student council and community project experience.',
            'reason_for_candidacy' => 'To represent students clearly and publish measurable progress.',
            'registered_at' => $now->subDays(2),
        ]);
        $pendingUser = User::query()->create([
            'name' => 'Pending Candidate',
            'email' => 'pending-candidate@example.test',
            'password' => Hash::make('Candidate!Pass2'),
        ]);
        Candidacy::query()->create([
            'user_id' => $pendingUser->getKey(),
            'election_id' => $election->getKey(),
            'status' => Candidacy::PENDING,
            'manifesto' => 'This pending candidate must not be published on the approved ballot.',
            'registered_at' => $now->subDay(),
        ]);

        return [$election, $approved];
    }

    private function catalogElection(
        string $title,
        ElectionStatus $status,
        CarbonImmutable $votingStart,
        CarbonImmutable $votingEnd,
        EligibilityRules $eligibility,
        User $admin,
    ): Election {
        return Election::query()->create([
            'title' => $title,
            'position_sought' => 'Student Representative',
            'description' => 'Published election information for the secure voter catalog.',
            'eligibility_rules' => $eligibility->toStorage(),
            'nomination_start' => $votingStart->subDays(2),
            'nomination_end' => $votingStart->subDay(),
            'voting_start' => $votingStart,
            'voting_end' => $votingEnd,
            'status' => $status->value,
            'payment_verified_at' => $votingStart->subDay(),
            'approved_by' => $admin->getKey(),
            'approved_at' => $votingStart->subDay(),
            'currency' => 'MYR',
        ]);
    }
}
