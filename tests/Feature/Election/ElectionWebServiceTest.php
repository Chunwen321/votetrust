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

    public function test_available_and_detail_endpoints_publish_voting_data_in_ifa_envelopes(): void
    {
        $now = CarbonImmutable::now();
        [$election, $approvedCandidacy] = $this->votingElection($now);

        $list = $this->getJson('/api/v1/elections/available?'.http_build_query([
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

        $detail = $this->getJson('/api/v1/elections/'.$election->getKey().'/availability?'.http_build_query([
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
            ->assertJsonCount(1, 'data.ballot')
            ->assertJsonStructure(['status', 'timestamp', 'data' => ['ballot']]);
    }

    public function test_provider_endpoints_return_failure_envelopes_for_invalid_requests(): void
    {
        $response = $this->getJson('/api/v1/elections/available?requestID=BAD');

        $response->assertUnprocessable()
            ->assertJsonPath('status', 'F')
            ->assertJsonPath('requestID', 'BAD')
            ->assertJsonStructure(['status', 'timestamp', 'errors']);
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
            ->assertJsonPath('data.lifecycleState', ElectionStatus::NOMINATION_OPEN->value)
            ->assertJsonStructure(['status', 'timestamp', 'data']);
        self::assertSame(ElectionStatus::NOMINATION_OPEN->value, $election->refresh()->status);
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
}
