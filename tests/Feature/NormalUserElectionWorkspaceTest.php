<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Models\VoterEnrollment;
use App\ValueObjects\EligibilityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class NormalUserElectionWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_normal_user_can_see_voting_and_organised_elections_together(): void
    {
        $member = User::factory()->create([
            'full_name' => 'Election Member',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $votingElection = Election::query()->create([
            'title' => 'Community Vote 2026',
            'status' => Election::NOMINATION_OPEN,
            'currency' => 'MYR',
        ]);
        $organisedElection = Election::query()->create([
            'organizer_id' => $member->getKey(),
            'title' => 'Faculty Representative Election',
            'status' => Election::DRAFT,
            'currency' => 'MYR',
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $member->getKey(),
            'election_id' => $votingElection->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ]);
        $this->fakeElectionSummary($votingElection);

        $this->actingAs($member)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('Voting elections')
            ->assertSee('Community Vote 2026')
            ->assertSee('href="'.route('elections.show', $votingElection->getKey()).'" aria-label="View election Community Vote 2026">View election', false)
            ->assertSee('Organised elections')
            ->assertSee('Faculty Representative Election')
            ->assertSee('Create an election');

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Use one VoteTrust account to vote in some elections and organise others.');
    }

    public function test_an_eligible_public_voting_election_is_visible_without_manual_enrollment(): void
    {
        $member = User::factory()->create([
            'full_name' => 'Ali',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $publicElection = Election::query()->create([
            'title' => 'VOTING001',
            'position_sought' => 'Student Representative',
            'description' => 'A public student election.',
            'eligibility_rules' => EligibilityRules::publicElection(16)->toStorage(),
            'voting_start' => now()->subHour(),
            'voting_end' => now()->addDay(),
            'status' => Election::VOTING_OPEN,
            'currency' => 'MYR',
        ]);
        $this->fakeElectionSummary($publicElection);

        $this->actingAs($member)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('VOTING001')
            ->assertSee('Voter access: <span class="badge status-approved">Public</span>', false)
            ->assertSee('href="'.route('voting.ballot', $publicElection->getKey()).'" aria-label="Open the ballot for VOTING001">Open ballot', false)
            ->assertViewHas('voterEnrollments', fn ($enrollments): bool => $enrollments->pluck('election_id')->all() === [$publicElection->getKey()]);

        $this->assertDatabaseMissing('voter', [
            'user_id' => $member->getKey(),
            'election_id' => $publicElection->getKey(),
        ]);
    }

    public function test_a_member_who_registers_later_automatically_discovers_a_published_public_election(): void
    {
        $publicElection = Election::query()->create([
            'title' => 'Future Public Student Election',
            'position_sought' => 'Student Representative',
            'description' => 'A published election created before this member registered.',
            'eligibility_rules' => EligibilityRules::publicElection(16)->toStorage(),
            'nomination_start' => now()->subHour(),
            'nomination_end' => now()->addDay(),
            'voting_start' => now()->addDays(2),
            'voting_end' => now()->addDays(3),
            'status' => Election::NOMINATION_OPEN,
            'currency' => 'MYR',
        ]);

        $member = User::factory()->create([
            'full_name' => 'Late Registered Member',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $this->fakeElectionSummary($publicElection);

        $this->actingAs($member)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('Future Public Student Election')
            ->assertSee('Voter access: <span class="badge status-approved">Public</span>', false)
            ->assertSee('href="'.route('elections.show', $publicElection).'" aria-label="View election Future Public Student Election">View election', false)
            ->assertViewHas('voterEnrollments', fn ($enrollments): bool => $enrollments
                ->pluck('election_id')
                ->all() === [$publicElection->getKey()]);

        $this->assertDatabaseMissing('voter', [
            'user_id' => $member->getKey(),
            'election_id' => $publicElection->getKey(),
        ]);
    }

    public function test_each_election_role_card_shows_its_next_date_and_time_with_a_clear_label(): void
    {
        $this->travelTo(Carbon::parse('2026-08-15 12:00:00'));

        $member = User::factory()->create([
            'full_name' => 'Election Schedule Member',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $candidateOrganizer = User::factory()->create([
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $votingElection = Election::query()->create([
            'title' => 'Voting Deadline Election',
            'eligibility_rules' => EligibilityRules::privateElection()->toStorage(),
            'nomination_start' => '2026-08-10 09:00:00',
            'nomination_end' => '2026-08-12 17:30:00',
            'voting_start' => '2026-08-15 09:00:00',
            'voting_end' => '2026-08-20 17:30:00',
            'status' => Election::VOTING_OPEN,
            'currency' => 'MYR',
        ]);
        Election::query()->create([
            'organizer_id' => $candidateOrganizer->getKey(),
            'title' => 'Nomination Deadline Election',
            'position_sought' => 'Student Representative',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => '2026-08-14 09:00:00',
            'nomination_end' => '2026-08-18 17:30:00',
            'voting_start' => '2026-08-19 09:00:00',
            'voting_end' => '2026-08-21 17:30:00',
            'status' => Election::NOMINATION_OPEN,
            'currency' => 'MYR',
        ]);
        Election::query()->create([
            'organizer_id' => $member->getKey(),
            'title' => 'Organiser Schedule Election',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => '2026-08-22 09:15:00',
            'nomination_end' => '2026-08-24 17:30:00',
            'voting_start' => '2026-08-25 09:00:00',
            'voting_end' => '2026-08-26 17:30:00',
            'status' => Election::DRAFT,
            'currency' => 'MYR',
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $member->getKey(),
            'election_id' => $votingElection->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ]);

        $this->fakeElectionSummary($votingElection);

        $this->actingAs($member)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('href="'.route('voting.ballot', $votingElection->getKey()).'" aria-label="Open the ballot for Voting Deadline Election">Open ballot', false)
            ->assertSeeTextInOrder([
                'Voting Deadline Election',
                'Voting closes',
                '20 Aug 2026, 5:30 PM',
                'Nomination Deadline Election',
                'Nominations close',
                '18 Aug 2026, 5:30 PM',
                'Organiser Schedule Election',
                'Planned nomination start',
                '22 Aug 2026, 9:15 AM',
            ]);
    }

    public function test_an_expired_nomination_is_hidden_from_opportunities_and_labelled_closed_on_details(): void
    {
        $this->travelTo(Carbon::parse('2026-08-21 15:28:01'));

        $member = User::factory()->create([
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $election = Election::query()->create([
            'title' => 'Ready to Open Voting',
            'position_sought' => 'Student Representative',
            'description' => 'Nominations have ended and the ballot is waiting for voting to open.',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => '2026-08-20 09:00:00',
            'nomination_end' => '2026-08-21 15:28:00',
            'voting_start' => '2026-08-22 09:00:00',
            'voting_end' => '2026-08-22 17:00:00',
            'status' => Election::NOMINATION_OPEN,
            'currency' => 'MYR',
        ]);

        $this->actingAs($member)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertDontSee('Ready to Open Voting');

        $this->actingAs($member)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSee('election-status-nomination-closed', false)
            ->assertSeeText('Nominations Closed')
            ->assertDontSeeText('Nomination Open');
    }

    public function test_a_closed_voter_election_remains_visible_with_a_results_link(): void
    {
        $member = User::factory()->create([
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $closedElection = Election::query()->create([
            'title' => 'Completed Community Election',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'voting_start' => now()->subDays(2),
            'voting_end' => now()->subDay(),
            'status' => Election::CLOSED,
            'currency' => 'MYR',
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $member->getKey(),
            'election_id' => $closedElection->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ]);
        $this->fakeElectionSummary($closedElection);

        $this->actingAs($member)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('Completed Community Election')
            ->assertSee('href="'.route('voting.results', $closedElection->getKey()).'"', false)
            ->assertSee('View results')
            ->assertDontSee('href="'.route('voting.ballot', $closedElection->getKey()).'"', false);
    }

    public function test_a_voting_open_election_is_hidden_after_its_voting_end_time(): void
    {
        $member = User::factory()->create([
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $endedElection = Election::query()->create([
            'title' => 'Ended Awaiting Closure',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'voting_start' => now()->subDays(2),
            'voting_end' => now()->subMinute(),
            'status' => Election::VOTING_OPEN,
            'currency' => 'MYR',
        ]);
        $this->fakeElectionSummary($endedElection);

        $this->actingAs($member)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertDontSee('Ended Awaiting Closure')
            ->assertViewHas('voterEnrollments', fn ($enrollments): bool => $enrollments->isEmpty());
    }

    public function test_rejected_private_voter_access_never_displays_a_ballot_link(): void
    {
        $member = User::factory()->create([
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $election = Election::query()->create([
            'title' => 'Restricted Community Election',
            'eligibility_rules' => EligibilityRules::privateElection()->toStorage(),
            'voting_start' => now()->subHour(),
            'voting_end' => now()->addHour(),
            'status' => Election::VOTING_OPEN,
            'currency' => 'MYR',
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $member->getKey(),
            'election_id' => $election->getKey(),
            'status' => VoterEnrollment::REJECTED,
        ]);
        $this->fakeElectionSummary($election);

        $this->actingAs($member)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('Voting access rejected')
            ->assertDontSee('href="'.route('voting.ballot', $election->getKey()).'"', false);
    }

    public function test_a_draft_is_private_to_its_organiser_until_it_is_published(): void
    {
        $organiser = User::factory()->create([
            'full_name' => 'Draft Election Organiser',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        $participant = User::factory()->create([
            'full_name' => 'Draft Election Participant',
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);
        Organizer::query()->create(['user_id' => $organiser->getKey()]);

        $draft = Election::query()->create([
            'organizer_id' => $organiser->getKey(),
            'title' => 'Unpublished Committee Draft',
            'position_sought' => 'Committee Representative',
            'description' => 'A draft election that only its organiser should be able to review.',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'nomination_start' => now()->addDay(),
            'nomination_end' => now()->addDays(2),
            'voting_start' => now()->addDays(3),
            'voting_end' => now()->addDays(4),
            'status' => Election::DRAFT,
            'currency' => 'MYR',
        ]);
        VoterEnrollment::query()->create([
            'user_id' => $participant->getKey(),
            'election_id' => $draft->getKey(),
            'status' => VoterEnrollment::APPROVED,
        ]);
        Candidacy::query()->create([
            'user_id' => $participant->getKey(),
            'election_id' => $draft->getKey(),
            'status' => Candidacy::APPROVED,
        ]);

        $this->fakeElectionSummary($draft);

        $this->actingAs($participant)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertViewHas('voterEnrollments', fn ($enrollments): bool => $enrollments->isEmpty())
            ->assertViewHas('candidateElections', fn ($elections): bool => $elections->isEmpty())
            ->assertDontSee('Unpublished Committee Draft');

        $this->actingAs($participant)
            ->get(route('voter.dashboard'))
            ->assertOk()
            ->assertViewHas('enrollments', fn ($enrollments): bool => $enrollments->isEmpty())
            ->assertDontSee('Unpublished Committee Draft');

        $this->actingAs($participant)
            ->get(route('elections.show', $draft))
            ->assertNotFound();

        $this->actingAs($organiser)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('Organised elections')
            ->assertSee('Unpublished Committee Draft')
            ->assertSee('Continue setup')
            ->assertSee(route('elections.edit', $draft))
            ->assertViewHas('organizedElections', fn ($elections): bool => $elections->contains(
                fn (Election $election): bool => $election->is($draft),
            ));

        $this->actingAs($organiser)
            ->get(route('elections.show', $draft))
            ->assertOk()
            ->assertSee('Unpublished Committee Draft')
            ->assertSee('Edit election');
    }

    public function test_a_normal_user_becomes_an_organiser_when_creating_a_first_election(): void
    {
        $member = User::factory()->create([
            'is_admin' => false,
            'account_status' => 'ACTIVE',
        ]);

        $this->assertDatabaseMissing('organizer', ['user_id' => $member->getKey()]);

        $this->actingAs($member)
            ->get(route('elections.create'))
            ->assertOk()
            ->assertSee('data-election-form novalidate', false)
            ->assertDontSee('name="hosting_fee"', false)
            ->assertSee('Public: MYR 100.00')
            ->assertSee('Private: MYR 120.00');

        $response = $this->actingAs($member)->post(route('elections.store'), [
            'title' => 'New Community Election',
            'position_sought' => 'Community Representative',
            'description' => 'A new election created by a normal user from the shared election workspace.',
            'eligibility_mode' => 'PUBLIC',
            'eligibility_minimum_age' => 18,
            'eligibility_nationality' => 'ANY',
            'nomination_start' => now()->addDay()->toDateTimeString(),
            'nomination_end' => now()->addDays(2)->toDateTimeString(),
            'voting_start' => now()->addDays(3)->toDateTimeString(),
            'voting_end' => now()->addDays(4)->toDateTimeString(),
            // These values are deliberately malicious/obsolete form fields.
            // The organizer must not be able to override VoteTrust pricing.
            'hosting_fee' => '9999.00',
            'currency' => 'USD',
        ]);

        $election = Election::query()->where('title', 'New Community Election')->firstOrFail();

        $response->assertRedirect(route('elections.show', $election));
        $this->assertDatabaseHas('organizer', ['user_id' => $member->getKey()]);
        $this->assertDatabaseHas('election', [
            'id' => $election->getKey(),
            'organizer_id' => $member->getKey(),
            'hosting_fee' => '100.00',
            'currency' => 'MYR',
            'status' => Election::DRAFT,
        ]);
        self::assertInstanceOf(Organizer::class, Organizer::query()->find($member->getKey()));
    }

    public function test_private_election_uses_the_fixed_rm120_hosting_fee(): void
    {
        $member = User::factory()->create(['is_admin' => false, 'account_status' => 'ACTIVE']);

        $this->actingAs($member)->post(route('elections.store'), [
            'title' => 'Private Faculty Election',
            'position_sought' => 'Faculty Representative',
            'description' => 'A private election with a system-controlled hosting fee. ',
            'eligibility_mode' => 'PRIVATE',
            'nomination_start' => now()->addDay()->toDateTimeString(),
            'nomination_end' => now()->addDays(2)->toDateTimeString(),
            'voting_start' => now()->addDays(3)->toDateTimeString(),
            'voting_end' => now()->addDays(4)->toDateTimeString(),
            'hosting_fee' => '1.00',
            'currency' => 'USD',
        ])->assertRedirect();

        $this->assertDatabaseHas('election', [
            'title' => 'Private Faculty Election',
            'hosting_fee' => '120.00',
            'currency' => 'MYR',
            'status' => Election::DRAFT,
        ]);
    }

    private function fakeElectionSummary(Election $election): void
    {
        config()->set('votetrust-user.services.election', [
            'base_url' => 'https://election.test',
            'api_key' => 'election-consumer-key',
            'connect_timeout_seconds' => 1,
            'timeout_seconds' => 2,
            'response_max_age_seconds' => 300,
        ]);
        config()->set('votetrust-user.services.voting', [
            'base_url' => '',
            'api_key' => '',
        ]);

        Http::preventStrayRequests();
        Http::fake(function (ClientRequest $request) use ($election) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if ($path === '/api/v1/elections/voter-catalog') {
                $isCatalogState = in_array($election->status, [Election::NOMINATION_OPEN, Election::VOTING_OPEN, Election::CLOSED], true);

                return Http::response([
                    'status' => 'S',
                    'message' => 'Voter election catalog retrieved.',
                    'requestID' => $query['requestID'] ?? null,
                    'timestamp' => now()->toAtomString(),
                    'data' => ['elections' => $isCatalogState ? [[
                        'electionId' => $election->getKey(),
                        'title' => $election->title,
                        'positionSought' => (string) $election->position_sought,
                        'description' => (string) $election->description,
                        'lifecycleState' => $election->status,
                        'votingAllowed' => $election->status === Election::VOTING_OPEN
                            && ($election->voting_start === null || $election->voting_start->lte(now()))
                            && ($election->voting_end === null || $election->voting_end->gte(now())),
                        'eligibility' => EligibilityRules::fromStored($election->eligibility_rules)->toArray(),
                        'nominationPeriod' => [
                            'start' => $election->nomination_start?->toAtomString(),
                            'end' => $election->nomination_end?->toAtomString(),
                        ],
                        'votingPeriod' => [
                            'start' => $election->voting_start?->toAtomString(),
                            'end' => $election->voting_end?->toAtomString(),
                        ],
                        'ballotCandidateCount' => 0,
                    ]] : []],
                ]);
            }

            return Http::response([
                'status' => 'S',
                'message' => 'Election summaries retrieved.',
                'requestID' => $query['requestID'] ?? null,
                'timestamp' => now()->toAtomString(),
                'data' => ['elections' => [[
                    'electionId' => $election->getKey(),
                    'title' => $election->title,
                    'lifecycleState' => $election->status,
                    'organizerUserId' => $election->organizer_id,
                    'nominationStart' => $election->nomination_start?->toAtomString(),
                    'nominationEnd' => $election->nomination_end?->toAtomString(),
                    'votingStart' => $election->voting_start?->toAtomString(),
                    'votingEnd' => $election->voting_end?->toAtomString(),
                    'updatedAt' => $election->updated_at?->toAtomString(),
                ]]],
            ]);
        });
    }
}
