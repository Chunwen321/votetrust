<?php

declare(strict_types=1);

namespace Tests\Feature\Election;

use App\Models\Candidacy;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Services\ElectionService;
use App\ValueObjects\ElectionTransitionRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ElectionAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_actors_use_their_display_name_while_admin_keeps_the_system_label(): void
    {
        $organizer = User::factory()->create([
            'name' => 'OrganizerAdmin',
            'full_name' => 'Maya Lim',
            'is_admin' => false,
        ]);
        $admin = User::factory()->create([
            'name' => 'InternalAdminAccount',
            'full_name' => 'VoteTrust System Administrator',
            'is_admin' => true,
        ]);

        self::assertSame('Maya Lim', $this->recordFor($organizer)->actorLabel());
        self::assertSame('SystemAdmin', $this->recordFor($admin)->actorLabel());
        self::assertSame('User #999999', (new ElectionTransitionRecord([
            'actor_user_id' => 999999,
        ]))->actorLabel());
    }

    public function test_audit_records_distinguish_lifecycle_transitions_from_domain_events(): void
    {
        $transition = new ElectionTransitionRecord([
            'type' => ElectionTransitionRecord::LIFECYCLE_TRANSITION,
            'from_state' => 'NOMINATION_OPEN',
            'to_state' => 'VOTING_OPEN',
        ]);
        $domainEvent = new ElectionTransitionRecord([
            'type' => ElectionTransitionRecord::DOMAIN_EVENT,
            'from_state' => 'NOMINATION_OPEN',
            'to_state' => 'NOMINATION_OPEN',
        ]);

        self::assertSame(ElectionTransitionRecord::LIFECYCLE_TRANSITION, $transition->type());
        self::assertTrue($transition->changesLifecycleState());
        self::assertSame(ElectionTransitionRecord::DOMAIN_EVENT, $domainEvent->type());
        self::assertFalse($domainEvent->changesLifecycleState());
    }

    public function test_legacy_audit_records_infer_their_type_from_the_recorded_states(): void
    {
        $transition = new ElectionTransitionRecord([
            'from_state' => 'NOMINATION_OPEN',
            'to_state' => 'VOTING_OPEN',
        ]);
        $domainEvent = new ElectionTransitionRecord([
            'from_state' => 'NOMINATION_OPEN',
            'to_state' => 'NOMINATION_OPEN',
        ]);

        self::assertSame(ElectionTransitionRecord::LIFECYCLE_TRANSITION, $transition->type());
        self::assertSame(ElectionTransitionRecord::DOMAIN_EVENT, $domainEvent->type());
    }

    public function test_candidate_review_is_stored_and_displayed_as_a_domain_event(): void
    {
        $organizer = User::factory()->create([
            'full_name' => 'Maya Lim',
            'is_admin' => false,
        ]);
        Organizer::query()->create(['user_id' => $organizer->getKey()]);
        $service = new ElectionService;
        $election = $service->create($organizer, [
            'title' => 'Audit Classification Election',
            'position_sought' => 'Representative',
            'description' => 'An election used to verify audit event classification and presentation.',
            'eligibility_mode' => 'PUBLIC',
            'eligibility_minimum_age' => 18,
            'eligibility_nationality' => 'ANY',
            'nomination_start' => now()->subDay(),
            'nomination_end' => now()->addDay(),
            'voting_start' => now()->addDays(2),
            'voting_end' => now()->addDays(3),
        ]);
        $election->forceFill(['status' => Election::NOMINATION_OPEN])->save();
        $candidate = User::factory()->create();
        $application = Candidacy::query()->create([
            'user_id' => $candidate->getKey(),
            'election_id' => $election->getKey(),
            'status' => Candidacy::PENDING,
        ]);

        self::assertSame(1, $service->reviewCandidates(
            $organizer,
            (int) $election->getKey(),
            [(int) $application->getKey() => Candidacy::APPROVED],
            'password',
            1,
        ));

        $history = $election->refresh()->transition_history_json;
        self::assertSame(ElectionTransitionRecord::LIFECYCLE_TRANSITION, $history[0]['type']);
        self::assertSame(ElectionTransitionRecord::DOMAIN_EVENT, $history[1]['type']);

        $this->actingAs($organizer)
            ->get(route('elections.show', $election))
            ->assertOk()
            ->assertSeeText('Revision 2')
            ->assertSeeText('Lifecycle effect')
            ->assertSeeText('State unchanged')
            ->assertSeeText('Maya Lim');
    }

    private function recordFor(User $actor): ElectionTransitionRecord
    {
        return new ElectionTransitionRecord([
            'actor_user_id' => $actor->getKey(),
        ]);
    }
}
