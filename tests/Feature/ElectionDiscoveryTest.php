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
            ->assertSee('Become a candidate')
            ->assertSee(route('candidate.applications.create', ['election_id' => $election->getKey()]));

        $this->actingAs($user)
            ->get(route('candidate.applications.create', ['election_id' => $election->getKey()]))
            ->assertOk()
            ->assertSee('value="'.$election->getKey().'" selected', false);
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
            ->get(route('candidate.applications.create'))
            ->assertOk()
            ->assertDontSee('Private Committee Election');
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
            ->assertSee('You have been assigned as a candidate');

        $this->actingAs($user)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSee('Private election')
            ->assertSee('You are assigned as a candidate')
            ->assertSee('Approved')
            ->assertDontSee('Become a candidate');
    }

    private function election(string $title, EligibilityRules $eligibility): Election
    {
        return Election::query()->create([
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
