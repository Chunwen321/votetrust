<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Models\VoterEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($member)
            ->get(route('elections.index'))
            ->assertOk()
            ->assertSee('Voting elections')
            ->assertSee('Community Vote 2026')
            ->assertSee('Organised elections')
            ->assertSee('Faculty Representative Election')
            ->assertSee('Create an election');

        $this->actingAs($member)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Use one VoteTrust account to vote in some elections and organise others.');
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
            ->assertSee('administrator will review this configuration');

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
            // The organizer must not be able to establish the hosting quote.
            'hosting_fee' => '9999.00',
            'currency' => 'USD',
        ]);

        $election = Election::query()->where('title', 'New Community Election')->firstOrFail();

        $response->assertRedirect(route('elections.show', $election));
        $this->assertDatabaseHas('organizer', ['user_id' => $member->getKey()]);
        $this->assertDatabaseHas('election', [
            'id' => $election->getKey(),
            'organizer_id' => $member->getKey(),
            'hosting_fee' => null,
            'currency' => 'MYR',
            'status' => Election::DRAFT,
        ]);
        self::assertInstanceOf(Organizer::class, Organizer::query()->find($member->getKey()));
    }
}
