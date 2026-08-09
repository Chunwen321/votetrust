<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Services;

use App\Contracts\Clock;
use App\Contracts\PaymentGateway;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ConcurrencyException;
use App\Models\Candidacy;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Security\ReauthenticationGuard;
use App\Security\SecurityAuditLogger;
use App\Services\Clock\SystemClock;
use App\Services\Payment\HttpPaymentGateway;
use App\States\ElectionLifecycle;
use App\States\ElectionStateFactory;
use App\States\ElectionStatus;
use App\ValueObjects\ElectionTransitionRecord;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class ElectionService
{
    private const ADMIN_ACTIONS = ['approve', 'reject'];

    private const CANDIDACY_PENDING = 'Pending';

    private const CANDIDACY_APPROVED = 'Approved';

    private const CANDIDACY_REJECTED = 'Rejected';

    private readonly ReauthenticationGuard $reauthentication;

    private readonly PaymentGateway $paymentGateway;

    private readonly Clock $clock;

    private readonly SecurityAuditLogger $auditLogger;

    private readonly ElectionStateFactory $stateFactory;

    public function __construct(
        ?ReauthenticationGuard $reauthentication = null,
        ?PaymentGateway $paymentGateway = null,
        ?Clock $clock = null,
        ?SecurityAuditLogger $auditLogger = null,
        ?ElectionStateFactory $stateFactory = null,
    ) {
        $this->reauthentication = $reauthentication ?? new ReauthenticationGuard;
        $this->paymentGateway = $paymentGateway ?? new HttpPaymentGateway;
        $this->clock = $clock ?? new SystemClock;
        $this->auditLogger = $auditLogger ?? new SecurityAuditLogger;
        $this->stateFactory = $stateFactory ?? new ElectionStateFactory;
    }

    /** @param array<string, mixed> $input */
    public function create(User $user, array $input): Election
    {
        if ($this->isAdmin($user)) {
            throw new AuthorizationException('A normal user account is required to create an election.');
        }

        // A normal user does not choose a permanent account role. Creating a
        // first election simply gives that user an organiser record, while
        // leaving their voter access for other elections unchanged.
        $organizer = Organizer::query()->firstOrCreate(['user_id' => $user->getKey()]);
        $valid = $this->normalizeInput($input);
        $requestId = $this->newRequestId();
        $now = $this->clock->now();

        $election = new Election;
        $election->forceFill($valid + [
            'organizer_id' => $organizer->getKey(),
            'status' => ElectionStatus::DRAFT->value,
            'version' => 1,
            'transition_history_json' => [[
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'action' => 'create',
                'from_state' => 'NONE',
                'to_state' => ElectionStatus::DRAFT->value,
                'metadata' => [],
                'created_at' => $now->toDateTimeString(),
            ]],
        ]);
        $election->save();

        $this->auditLogger->log('election_created', [
            'election_id' => $election->getKey(),
            'actor_user_id' => $user->getKey(),
            'request_id' => $requestId,
        ]);

        return $election;
    }

    /** @param array<string, mixed> $input */
    public function update(User $user, int $electionId, array $input): Election
    {
        return DB::transaction(function () use ($user, $electionId, $input): Election {
            $election = Election::query()->lockForUpdate()->find($electionId);
            if (! $election instanceof Election) {
                throw (new ModelNotFoundException)->setModel(Election::class, [$electionId]);
            }
            $this->assertOwnedBy($user, $election);
            if ($election->status !== ElectionStatus::DRAFT->value) {
                throw new BusinessRuleException('Only a draft election can be edited.');
            }

            $expectedVersion = $this->assertExpectedVersion($election, $input['version'] ?? null);
            $election->forceFill($this->normalizeInput($input));
            $this->persistWithVersion($election, $expectedVersion);

            return $election->refresh();
        });
    }

    /** @param array<string, mixed> $input */
    public function transition(
        User $actor,
        int $electionId,
        string $action,
        string $currentPassword,
        array $input = [],
    ): Election {
        $snapshot = Election::query()->find($electionId);
        if (! $snapshot instanceof Election) {
            throw (new ModelNotFoundException)->setModel(Election::class, [$electionId]);
        }
        $this->assertActionActor($actor, $snapshot, $action);
        $this->reauthentication->assertPassword($actor, $currentPassword, "election:{$electionId}:{$action}");

        return $this->performTransition($actor, $electionId, $action, $input, $this->newRequestId());
    }

    /** @param array<string, mixed> $input */
    public function transitionFromAdminApi(
        User $admin,
        int $electionId,
        string $action,
        array $input,
        string $requestId,
    ): Election {
        if (! in_array($action, self::ADMIN_ACTIONS, true)) {
            throw new AuthorizationException('The Admin API only accepts approval decisions.');
        }

        return $this->performTransition($admin, $electionId, $action, $input, $requestId);
    }

    /** @param array<string, mixed> $input */
    private function performTransition(
        User $actor,
        int $electionId,
        string $action,
        array $input,
        string $requestId,
    ): Election {
        return DB::transaction(function () use ($actor, $electionId, $action, $input, $requestId): Election {
            $election = Election::query()->lockForUpdate()->find($electionId);
            if (! $election instanceof Election) {
                throw (new ModelNotFoundException)->setModel(Election::class, [$electionId]);
            }
            $this->assertActionActor($actor, $election, $action);
            $expectedVersion = $this->assertExpectedVersion($election, $input['version'] ?? null);

            foreach ($this->transitionLogs($election, false) as $record) {
                if (hash_equals((string) $record->get('request_id', ''), $requestId)) {
                    return $election;
                }
            }

            if ($action === 'start_voting') {
                $summary = $this->candidateSummary($election);
                $input['approved_candidate_count'] = $summary['approved'];
                $input['pending_candidate_count'] = $summary['pending'];
            }

            $lifecycle = new ElectionLifecycle($election, $this->stateFactory);
            $context = new TransitionContext(
                $actor,
                $this->clock->now(),
                $requestId,
                $this->paymentGateway,
                $this->auditLogger,
                $input,
            );
            $lifecycle->transition($action, $context);
            $transition = $lifecycle->lastTransition();
            if ($transition === null) {
                throw new BusinessRuleException('No lifecycle transition was produced.');
            }

            $this->appendTransition($election, [
                'actor_user_id' => $actor->getKey(),
                'request_id' => $requestId,
                'action' => $transition['action'],
                'from_state' => $transition['from'],
                'to_state' => $transition['to'],
                'metadata' => $transition['metadata'],
                'created_at' => $this->clock->now()->toDateTimeString(),
            ]);
            $this->persistWithVersion($election, $expectedVersion);

            $this->auditLogger->log('election_lifecycle_transition', [
                'election_id' => $election->getKey(),
                'actor_user_id' => $actor->getKey(),
                'request_id' => $requestId,
                'from' => $transition['from'],
                'to' => $transition['to'],
                'action' => $transition['action'],
            ]);

            return $election->refresh();
        });
    }

    public function findOwned(User $user, int $electionId): Election
    {
        $election = Election::query()->find($electionId);
        if (! $election instanceof Election) {
            throw (new ModelNotFoundException)->setModel(Election::class, [$electionId]);
        }
        $this->assertOwnedBy($user, $election);

        return $election;
    }

    public function findForAdmin(User $user, int $electionId): Election
    {
        $this->assertAdmin($user);

        return Election::query()->findOrFail($electionId);
    }

    /** @return list<array<string, mixed>> */
    public function actions(Election $election, User $actor): array
    {
        $audience = $this->isAdmin($actor) ? 'admin' : 'organizer';
        if ($audience === 'organizer' && ! $this->isOwnedBy($actor, $election)) {
            return [];
        }

        return array_values(array_filter(
            $this->stateFactory->make((string) $election->status)->actions($election),
            static fn (array $item): bool => $item['audience'] === $audience,
        ));
    }

    /** @return array<string, mixed> */
    public function availability(Election $election, ?CarbonImmutable $at = null): array
    {
        $now = $at ?? $this->clock->now();
        $state = $this->stateFactory->make((string) $election->status);
        $eligibilityRules = EligibilityRules::fromStored($election->eligibility_rules)->toArray();

        return [
            'electionId' => (int) $election->getKey(),
            'title' => (string) $election->title,
            'description' => (string) $election->description,
            'lifecycleState' => $state->name(),
            'lifecycleLabel' => $state->label(),
            'hostingPaymentVerified' => $this->paymentVerified($election),
            'administratorApproved' => $this->adminApproved($election),
            'eligibilityRules' => $eligibilityRules,
            'eligibility' => $eligibilityRules,
            'candidateRegistrationAllowed' => $state->canRegisterCandidate($election, $now),
            'votingAllowed' => $state->canVote($election, $now),
            'ballot' => $this->approvedBallot($election),
            'nominationPeriod' => [
                'start' => CarbonImmutable::parse($election->nomination_start)->toAtomString(),
                'end' => CarbonImmutable::parse($election->nomination_end)->toAtomString(),
            ],
            'votingPeriod' => [
                'start' => CarbonImmutable::parse($election->voting_start)->toAtomString(),
                'end' => CarbonImmutable::parse($election->voting_end)->toAtomString(),
            ],
            'checkedAt' => $now->toAtomString(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function availableForVoting(?CarbonImmutable $at = null): array
    {
        $now = $at ?? $this->clock->now();
        $state = $this->stateFactory->make(ElectionStatus::VOTING_OPEN->value);

        return Election::query()
            ->where('status', ElectionStatus::VOTING_OPEN->value)
            ->withCount([
                'candidacies as approved_ballot_count' => static fn ($query) => $query
                    ->where('status', self::CANDIDACY_APPROVED),
            ])
            ->orderBy('voting_end')
            ->get()
            ->filter(function (Election $election) use ($state, $now): bool {
                try {
                    return $state->canVote($election, $now);
                } catch (BusinessRuleException) {
                    return false;
                }
            })
            ->map(static function (Election $election): array {
                return [
                    'electionId' => (int) $election->getKey(),
                    'title' => (string) $election->title,
                    'description' => (string) $election->description,
                    'lifecycleState' => ElectionStatus::VOTING_OPEN->value,
                    'eligibilityRules' => EligibilityRules::fromStored($election->eligibility_rules)->toArray(),
                    'eligibility' => EligibilityRules::fromStored($election->eligibility_rules)->toArray(),
                    'votingPeriod' => [
                        'start' => CarbonImmutable::parse($election->voting_start)->toAtomString(),
                        'end' => CarbonImmutable::parse($election->voting_end)->toAtomString(),
                    ],
                    'ballotCandidateCount' => (int) $election->getAttribute('approved_ballot_count'),
                ];
            })
            ->values()
            ->all();
    }

    /** @return Collection<int, Candidacy> */
    public function candidateApplications(Election $election): Collection
    {
        return Candidacy::query()
            ->where('election_id', $election->getKey())
            ->orderBy('registered_at')
            ->get();
    }

    /** @return list<array{id:int,candidateId:int,name:string,position:string,manifesto:string}> */
    private function approvedBallot(Election $election): array
    {
        return Candidacy::query()
            ->with('user')
            ->where('election_id', $election->getKey())
            ->where('status', self::CANDIDACY_APPROVED)
            ->orderBy('position_sought')
            ->orderBy('id')
            ->get()
            ->map(static function (Candidacy $candidacy): array {
                $candidate = $candidacy->user;
                $name = trim((string) $candidate?->display_name);

                return [
                    'id' => (int) $candidacy->getKey(),
                    'candidateId' => (int) $candidacy->user_id,
                    'name' => $name !== '' ? $name : 'Candidate '.$candidacy->user_id,
                    'position' => (string) $candidacy->position_sought,
                    'manifesto' => (string) $candidacy->manifesto,
                ];
            })
            ->all();
    }

    /** @return array{applications:Collection<int,Candidacy>,total:int,pending:int,approved:int,rejected:int,ready:bool} */
    public function candidateSummary(Election $election): array
    {
        $applications = $this->candidateApplications($election);
        $summary = ['applications' => $applications, 'total' => $applications->count(), 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'ready' => false];
        foreach ($applications as $application) {
            match (strtoupper((string) $application->status)) {
                'APPROVED' => $summary['approved']++,
                'REJECTED' => $summary['rejected']++,
                default => $summary['pending']++,
            };
        }
        $summary['ready'] = $summary['approved'] > 0 && $summary['pending'] === 0;

        return $summary;
    }

    /** @param array<int|string, mixed> $decisions */
    public function reviewCandidates(
        User $user,
        int $electionId,
        array $decisions,
        string $currentPassword,
        ?int $submittedVersion = null,
    ): int {
        $snapshot = $this->findOwned($user, $electionId);
        if ($snapshot->status !== ElectionStatus::NOMINATION_OPEN->value) {
            throw new BusinessRuleException('Candidate decisions are available only while nominations are open.');
        }
        $this->reauthentication->assertPassword($user, $currentPassword, "election:{$electionId}:candidate_review");

        return DB::transaction(function () use ($user, $electionId, $decisions, $submittedVersion): int {
            $election = Election::query()->lockForUpdate()->findOrFail($electionId);
            $this->assertOwnedBy($user, $election);
            if ($election->status !== ElectionStatus::NOMINATION_OPEN->value) {
                throw new BusinessRuleException('Candidate decisions are available only while nominations are open.');
            }
            $expectedVersion = $this->assertExpectedVersion($election, $submittedVersion);
            $applications = Candidacy::query()->where('election_id', $electionId)->lockForUpdate()->get()->keyBy(fn ($item) => (int) $item->getKey());
            $allowed = [self::CANDIDACY_PENDING, self::CANDIDACY_APPROVED, self::CANDIDACY_REJECTED];
            $changes = [];

            foreach ($decisions as $applicationId => $decision) {
                $id = filter_var($applicationId, FILTER_VALIDATE_INT);
                $status = ucfirst(strtolower(trim((string) $decision)));
                if ($id === false || ! $applications->has((int) $id)) {
                    throw new BusinessRuleException('A candidate decision does not belong to this election.');
                }
                if (! in_array($status, $allowed, true)) {
                    throw new BusinessRuleException('Select a valid candidate decision.');
                }
                $application = $applications->get((int) $id);
                if ((string) $application->status === $status) {
                    continue;
                }
                $application->status = $status;
                $application->save();
                $changes[] = ['candidacy_id' => $application->getKey(), 'user_id' => $application->user_id, 'decision' => $status];
            }

            if ($changes === []) {
                return 0;
            }

            $requestId = $this->newRequestId();
            $this->appendTransition($election, [
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'action' => 'review_candidates',
                'from_state' => ElectionStatus::NOMINATION_OPEN->value,
                'to_state' => ElectionStatus::NOMINATION_OPEN->value,
                'metadata' => ['candidate_decisions' => $changes],
                'created_at' => $this->clock->now()->toDateTimeString(),
            ]);
            $this->persistWithVersion($election, $expectedVersion);
            $this->auditLogger->log('candidate_ballot_decisions_updated', [
                'election_id' => $electionId,
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'candidate_decisions' => $changes,
            ]);

            return count($changes);
        });
    }

    /** @return list<ElectionTransitionRecord> */
    public function transitionLogs(Election $election, bool $newestFirst = true): array
    {
        $history = $this->storedArray($election->transition_history_json);
        if (! is_array($history)) {
            return [];
        }
        if ($newestFirst) {
            $history = array_reverse($history);
        }

        return array_values(array_map(
            static fn (array $record): ElectionTransitionRecord => new ElectionTransitionRecord($record),
            array_filter($history, 'is_array'),
        ));
    }

    public function isAdmin(User $user): bool
    {
        if (method_exists($user, 'isAdmin')) {
            return (bool) $user->isAdmin();
        }

        return (bool) ($user->is_admin ?? false)
            || strtolower((string) ($user->role ?? '')) === 'admin';
    }

    public function requireOrganizer(User $user): Organizer
    {
        if ($this->isAdmin($user)) {
            throw new AuthorizationException('An organiser account is required.');
        }
        $organizer = Organizer::query()->where('user_id', $user->getKey())->first();
        if (! $organizer instanceof Organizer) {
            throw new AuthorizationException('An organiser account is required.');
        }

        return $organizer;
    }

    private function assertAdmin(User $user): void
    {
        if (! $this->isAdmin($user)) {
            throw new AuthorizationException('A system administrator account is required.');
        }
    }

    private function assertOwnedBy(User $user, Election $election): void
    {
        $this->requireOrganizer($user);
        if (! $this->isOwnedBy($user, $election)) {
            throw new AuthorizationException('Only the organiser who created this election can manage it.');
        }
    }

    private function isOwnedBy(User $user, Election $election): bool
    {
        $organizer = Organizer::query()->find($election->organizer_id);

        return (int) ($organizer?->user_id ?? 0) === (int) $user->getKey();
    }

    private function assertActionActor(User $actor, Election $election, string $action): void
    {
        if (in_array($action, self::ADMIN_ACTIONS, true)) {
            $this->assertAdmin($actor);
            if ($this->isOwnedBy($actor, $election)) {
                throw new AuthorizationException('An administrator cannot approve their own election.');
            }

            return;
        }

        $this->assertOwnedBy($actor, $election);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
        $mode = strtoupper(trim((string) ($input['eligibility_mode'] ?? EligibilityRules::PUBLIC)));
        $rules = $mode === EligibilityRules::RESTRICTED
            ? EligibilityRules::restricted(
                ($input['eligibility_minimum_age'] ?? '') === '' ? null : (int) $input['eligibility_minimum_age'],
                strtoupper((string) ($input['eligibility_nationality'] ?? 'ANY')),
            )
            : EligibilityRules::publicElection();

        return [
            'title' => trim((string) $input['title']),
            'description' => trim((string) $input['description']),
            'eligibility_rules' => $rules->toStorage(),
            'nomination_start' => CarbonImmutable::parse($input['nomination_start'])->toDateTimeString(),
            'nomination_end' => CarbonImmutable::parse($input['nomination_end'])->toDateTimeString(),
            'voting_start' => CarbonImmutable::parse($input['voting_start'])->toDateTimeString(),
            'voting_end' => CarbonImmutable::parse($input['voting_end'])->toDateTimeString(),
            'hosting_fee' => number_format((float) $input['hosting_fee'], 2, '.', ''),
            'currency' => strtoupper(trim((string) ($input['currency'] ?? 'MYR'))),
        ];
    }

    /** @param array<string, mixed> $record */
    private function appendTransition(Election $election, array $record): void
    {
        $history = $this->storedArray($election->transition_history_json);
        $history[] = $record;
        $election->transition_history_json = $history;
    }

    /** @return array<int, mixed> */
    private function storedArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) ($value ?: '[]'), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function assertExpectedVersion(Election $election, mixed $submittedVersion): int
    {
        $stored = max(1, (int) ($election->version ?? 1));
        if ($submittedVersion !== null && (int) $submittedVersion !== $stored) {
            throw new ConcurrencyException;
        }

        return $stored;
    }

    private function persistWithVersion(Election $election, int $expectedVersion): void
    {
        $attributes = $election->getDirty();
        unset($attributes[$election->getKeyName()], $attributes['created_at'], $attributes['updated_at'], $attributes['version']);
        $attributes['version'] = $expectedVersion + 1;
        if ($election->usesTimestamps()) {
            $attributes[$election->getUpdatedAtColumn()] = $this->clock->now()->toDateTimeString();
        }

        $updated = Election::query()
            ->whereKey($election->getKey())
            ->where('version', $expectedVersion)
            ->update($attributes);
        if ($updated !== 1) {
            throw new ConcurrencyException;
        }

        $election->forceFill($attributes)->syncOriginal();
    }

    private function paymentVerified(Election $election): bool
    {
        return $election->payment_verified_at !== null && $election->payment_verified_at !== '';
    }

    private function adminApproved(Election $election): bool
    {
        return (int) $election->approved_by > 0 && $election->approved_at !== null && $election->approved_at !== '';
    }

    private function newRequestId(): string
    {
        return 'EL-'.strtoupper(bin2hex(random_bytes(12)));
    }
}
