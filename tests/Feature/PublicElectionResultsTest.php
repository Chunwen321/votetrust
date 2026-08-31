<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\VotingIntegration;
use App\Models\Election;
use App\Models\User;
use App\Models\Vote;
use App\ValueObjects\EligibilityRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class PublicElectionResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_ranked_aggregate_results_after_an_election_closes(): void
    {
        $election = Election::query()->create([
            'title' => 'Student Council Final',
            'position_sought' => 'President',
            'description' => 'The completed student council election.',
            'status' => Election::CLOSED,
        ]);
        $aisha = User::factory()->create(['full_name' => 'Aisha Rahman']);
        $daniel = User::factory()->create(['full_name' => 'Daniel Tan']);

        foreach ([$aisha, $aisha, $aisha, $daniel, $daniel] as $candidate) {
            Vote::query()->create([
                'election_id' => $election->getKey(),
                'voter_id' => User::factory()->create()->getKey(),
                'candidate_id' => $candidate->getKey(),
                'request_id' => 'RESULT-'.str()->random(16),
                'cast_at' => now(),
            ]);
        }

        $this->mock(VotingIntegration::class, function (MockInterface $mock) use ($election, $aisha, $daniel): void {
            $mock->shouldReceive('election')
                ->once()
                ->withArgs(fn (int $electionId): bool => $electionId === $election->getKey())
                ->andReturn($this->electionContract($election, Election::CLOSED, [$daniel, $aisha]));
        });

        $this->get(route('voting.results', $election))
            ->assertOk()
            ->assertSeeText('Final results')
            ->assertSeeText('Official tally')
            ->assertSeeText('Aisha Rahman')
            ->assertSeeText('Daniel Tan')
            ->assertSeeText('Winner')
            ->assertSeeText('60.0%')
            ->assertSeeText('40.0%')
            ->assertSeeText('Ballot privacy is preserved')
            ->assertViewHas('totalVotes', 5)
            ->assertViewHas('results', fn (array $results): bool => $results[0]['candidateId'] === $aisha->getKey()
                && $results[0]['votes'] === 3
                && $results[0]['isWinner'] === true
                && $results[1]['candidateId'] === $daniel->getKey()
                && $results[1]['votes'] === 2
            );
    }

    public function test_results_are_not_released_before_the_election_closes(): void
    {
        $election = Election::query()->create([
            'title' => 'Voting Still Open',
            'position_sought' => 'President',
            'status' => Election::VOTING_OPEN,
        ]);
        $candidate = User::factory()->create(['full_name' => 'Aisha Rahman']);

        $this->mock(VotingIntegration::class, function (MockInterface $mock) use ($election, $candidate): void {
            $mock->shouldReceive('election')
                ->once()
                ->andReturn($this->electionContract($election, Election::VOTING_OPEN, [$candidate]));
        });

        $this->get(route('voting.results', $election))->assertNotFound();
    }

    public function test_closed_election_details_link_to_the_published_results(): void
    {
        $member = User::factory()->create(['is_admin' => false]);
        $election = Election::query()->create([
            'title' => 'Completed Community Election',
            'position_sought' => 'Representative',
            'description' => 'A completed public election.',
            'eligibility_rules' => EligibilityRules::publicElection()->toStorage(),
            'status' => Election::CLOSED,
        ]);

        $this->actingAs($member)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSee('href="'.route('voting.results', $election).'"', false)
            ->assertSeeText('View results');
    }

    /**
     * @param  list<User>  $candidates
     * @return array<string, mixed>
     */
    private function electionContract(Election $election, string $status, array $candidates): array
    {
        return [
            'id' => (int) $election->getKey(),
            'title' => (string) $election->title,
            'position' => (string) $election->position_sought,
            'description' => (string) $election->description,
            'eligibilityRules' => 'Public election',
            'eligibility' => EligibilityRules::publicElection()->toArray(),
            'status' => $status,
            'votingAllowed' => false,
            'nominationPeriod' => ['start' => null, 'end' => null],
            'votingPeriod' => ['start' => null, 'end' => null],
            'candidacies' => array_map(static fn (User $candidate, int $index): array => [
                'id' => $index + 1,
                'candidateId' => (int) $candidate->getKey(),
                'name' => $candidate->display_name,
                'position' => (string) $election->position_sought,
                'manifesto' => '',
            ], $candidates, array_keys($candidates)),
        ];
    }
}
