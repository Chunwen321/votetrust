<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\User;
use App\ValueObjects\EligibilityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ElectionDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_discover_and_view_a_public_election_then_open_its_candidate_form(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $election = $this->election('Public Student Election', EligibilityRules::publicElection());

        $this->actingAs($user)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('Election opportunities')
            ->assertSee('Public Student Election')
            ->assertSee(route('elections.show', $election));

        $this->actingAs($user)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSee('Public election')
            ->assertSee('election-details-page', false)
            ->assertSee('Nomination period')
            ->assertSee('Voting period')
            ->assertSee('Starts')
            ->assertSee('Ends')
            ->assertSee('Apply as candidate')
            ->assertSee(route('candidate.applications.create', $election));

        $this->actingAs($user)
            ->get(route('candidate.applications.create', $election))
            ->assertOk()
            ->assertSee('Selected election')
            ->assertSee($election->title)
            ->assertSee($election->position_sought)
            ->assertDontSee('name="election_id"', false);
    }

    public function test_a_private_election_is_hidden_from_users_without_a_candidate_assignment(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $election = $this->election(
            'Private Committee Election',
            EligibilityRules::privateElection(),
        );

        $this->actingAs($user)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertDontSee('Private Committee Election');

        $this->actingAs($user)
            ->get(route('elections.show', $election))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('candidate.applications.create', $election))
            ->assertNotFound();
    }

    public function test_an_assigned_candidate_can_view_a_private_election_and_assignment_status(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $election = $this->election(
            'Assigned Board Election',
            EligibilityRules::privateElection(),
        );
        Candidacy::query()->create([
            'user_id' => $user->getKey(),
            'election_id' => $election->getKey(),
            'status' => Candidacy::APPROVED,
        ]);

        $this->actingAs($user)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('Assigned Board Election')
            ->assertSee('Private candidate assignment:');

        $this->actingAs($user)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSee('Private election')
            ->assertSee('Your candidate application')
            ->assertSee('Approved')
            ->assertDontSee('Apply as candidate');
    }

    public function test_candidate_listing_keeps_real_opportunities_and_existing_applications_only(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $openOpportunity = $this->election('Open Candidate Opportunity', EligibilityRules::publicElection());
        $expiredOpportunity = $this->election('Expired Candidate Opportunity', EligibilityRules::publicElection());
        $expiredOpportunity->forceFill([
            'nomination_start' => now()->subDays(3),
            'nomination_end' => now()->subDay(),
        ])->save();
        $unrelatedVotingElection = $this->election('Unrelated Voting Election', EligibilityRules::publicElection());
        $unrelatedVotingElection->forceFill(['status' => Election::VOTING_OPEN])->save();
        $existingApplication = $this->election('Existing Candidate Application', EligibilityRules::publicElection());
        $existingApplication->forceFill([
            'status' => Election::CLOSED,
            'nomination_start' => now()->subDays(5),
            'nomination_end' => now()->subDays(4),
            'voting_start' => now()->subDays(3),
            'voting_end' => now()->subDays(2),
        ])->save();
        Candidacy::query()->create([
            'user_id' => $user->getKey(),
            'election_id' => $existingApplication->getKey(),
            'status' => Candidacy::APPROVED,
        ]);

        $this->actingAs($user)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('Open Candidate Opportunity')
            ->assertSee('Existing Candidate Application')
            ->assertSee('Your application status:')
            ->assertDontSee('Expired Candidate Opportunity')
            ->assertDontSee('Unrelated Voting Election')
            ->assertViewHas('candidateElections', fn ($elections): bool => $elections
                ->pluck('id')
                ->all() === [$existingApplication->getKey(), $openOpportunity->getKey()])
            ->assertViewHas('candidateApplicationsOpen', fn ($states): bool => ($states[$openOpportunity->getKey()] ?? false)
                && ! ($states[$existingApplication->getKey()] ?? true));
    }

    private function election(string $title, EligibilityRules $eligibility): Election
    {
        $organizer = User::factory()->create(['is_admin' => false]);

        return Election::query()->create([
            'organizer_id' => $organizer->getKey(),
            'title' => $title,
            'position_sought' => 'Student Representative',
            'description' => 'Enough election detail for a user to understand this candidate opportunity.',
            'eligibility_rules' => $eligibility->toStorage(),
            'status' => Election::NOMINATION_OPEN,
            'nomination_start' => now()->subDay(),
            'nomination_end' => now()->addDay(),
            'voting_start' => now()->addDays(2),
            'voting_end' => now()->addDays(3),
            'currency' => 'MYR',
        ]);
    }
}
