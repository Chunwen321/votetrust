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
use App\Support\Identity;
use App\Support\PublicElectionExpiry;
use App\ValueObjects\ElectionTransitionRecord;
use App\ValueObjects\EligibilityRules;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ElectionService
{
    private const ADMIN_ACTIONS = ['verify_payment', 'approve', 'return_for_payment', 'reject'];

    private const CANDIDACY_PENDING = 'Pending';

    private const CANDIDACY_APPROVED = 'Approved';

    private const CANDIDACY_REJECTED = 'Rejected';

    private readonly ReauthenticationGuard $reauthentication;

    private readonly PaymentGateway $paymentGateway;

    private readonly Clock $clock;

    private readonly SecurityAuditLogger $auditLogger;

    private readonly ElectionStateFactory $stateFactory;

    private readonly VoterService $voters;

    public function __construct(
        ?ReauthenticationGuard $reauthentication = null,
        ?PaymentGateway $paymentGateway = null,
        ?Clock $clock = null,
        ?SecurityAuditLogger $auditLogger = null,
        ?ElectionStateFactory $stateFactory = null,
        ?VoterService $voters = null,
    ) {
        $this->reauthentication = $reauthentication ?? new ReauthenticationGuard;
        $this->paymentGateway = $paymentGateway ?? new HttpPaymentGateway;
        $this->clock = $clock ?? new SystemClock;
        $this->auditLogger = $auditLogger ?? new SecurityAuditLogger;
        $this->stateFactory = $stateFactory ?? new ElectionStateFactory;
        $this->voters = $voters ?? app(VoterService::class);
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
                'type' => ElectionTransitionRecord::LIFECYCLE_TRANSITION,
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

        if (EligibilityRules::fromStored($election->eligibility_rules)->mode() === EligibilityRules::PRIVATE) {
            $enrollment = $this->voters->ensureOrganizerEnrollment(
                (int) $election->getKey(),
                (int) $user->getKey(),
            );
            $election->forceFill([
                'private_voter_count' => $enrollment['totalAssigned'],
            ])->save();
        }

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
            $attributes = $this->normalizeInput($input);
            $currentMode = EligibilityRules::fromStored($election->eligibility_rules)->mode();
            $updatedMode = EligibilityRules::fromStored($attributes['eligibility_rules'])->mode();
            if ($currentMode !== $updatedMode) {
                // A roster belongs to a specific private-election definition.
                // Switching modes always requires a fresh private roster before payment.
                $attributes += [
                    'private_voter_list_uploaded_at' => null,
                    'private_voter_count' => 0,
                    'private_voter_unmatched_count' => 0,
                ];
            }
            $election->forceFill($attributes);
            $this->persistWithVersion($election, $expectedVersion);

            if ($updatedMode === EligibilityRules::PRIVATE) {
                $enrollment = $this->voters->ensureOrganizerEnrollment(
                    (int) $election->getKey(),
                    (int) $user->getKey(),
                );
                $election->forceFill([
                    'private_voter_count' => $enrollment['totalAssigned'],
                ])->save();
            }

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
            throw new AuthorizationException('The Admin API only accepts payment-verification and approval decisions.');
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

    /**
     * Lazily expires an unpublished public election before any payment work.
     * This does not require reauthentication because the clock, not the actor,
     * makes the terminal lifecycle decision.
     */
    public function expireOverduePublicElection(User $actor, int $electionId, string $trigger): ?Election
    {
        return DB::transaction(function () use ($actor, $electionId, $trigger): ?Election {
            $election = Election::query()->lockForUpdate()->find($electionId);
            if (! $election instanceof Election) {
                throw (new ModelNotFoundException)->setModel(Election::class, [$electionId]);
            }

            if ($this->isAdmin($actor)) {
                $this->assertAdmin($actor);
            } else {
                if ((int) $election->organizer_id !== (int) $actor->getKey()) {
                    $this->assertOwnedBy($actor, $election);
                }
            }

            if ($election->status === ElectionStatus::EXPIRED->value) {
                return $election;
            }

            $now = $this->clock->now();
            if (! PublicElectionExpiry::shouldExpire($election, $now)) {
                return null;
            }

            $expectedVersion = $this->assertExpectedVersion($election, $election->version);
            $requestId = $this->newRequestId();
            $from = (string) $election->status;
            $metadata = PublicElectionExpiry::metadata($election, $trigger);
            $election->status = ElectionStatus::EXPIRED->value;
            $this->appendTransition($election, [
                'actor_user_id' => $actor->getKey(),
                'request_id' => $requestId,
                'action' => 'expire_schedule',
                'from_state' => $from,
                'to_state' => ElectionStatus::EXPIRED->value,
                'metadata' => $metadata,
                'created_at' => $now->toDateTimeString(),
            ]);
            $this->persistWithVersion($election, $expectedVersion);
            $this->auditLogger->log('election_schedule_expired', [
                'election_id' => $election->getKey(),
                'actor_user_id' => $actor->getKey(),
                'request_id' => $requestId,
                'from' => $from,
                'trigger' => $trigger,
                'nomination_end' => $metadata['nomination_end'],
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

    public function reauthenticateOrganizer(
        User $user,
        int $electionId,
        string $currentPassword,
        string $operation,
    ): Election {
        $election = $this->findOwned($user, $electionId);
        $this->reauthentication->assertPassword(
            $user,
            $currentPassword,
            "election:{$electionId}:{$operation}",
        );

        return $election;
    }

    public function findForAdmin(User $user, int $electionId): Election
    {
        $this->assertAdmin($user);

        return Election::query()->findOrFail($electionId);
    }

    /**
     * Read-only payment status used to render the organiser's lifecycle gate.
     * The transition repeats this check and remains the authoritative guard.
     *
     * @return array{confirmed:bool,status:string,label:string,message:string}
     */
    public function hostingPaymentReadiness(User $user, Election $election): array
    {
        $this->assertOwnedBy($user, $election);

        if (PublicElectionExpiry::shouldExpire($election, $this->clock->now())) {
            return [
                'confirmed' => false,
                'status' => ElectionStatus::EXPIRED->value,
                'label' => 'Schedule expired',
                'message' => PublicElectionExpiry::MESSAGE,
            ];
        }

        if ($election->status !== ElectionStatus::PENDING_PAYMENT->value) {
            return [
                'confirmed' => $this->paymentVerified($election),
                'status' => (string) ($election->payment_provider_status ?: 'NOT_APPLICABLE'),
                'label' => $this->paymentVerified($election) ? 'Payment confirmed' : 'Not awaiting payment',
                'message' => 'This election is not currently awaiting a hosting payment.',
            ];
        }

        try {
            $result = $this->paymentGateway->verifyHostingPayment(
                $election,
                null,
                $this->newRequestId(),
                $this->clock->now(),
            );
        } catch (BusinessRuleException) {
            return [
                'confirmed' => false,
                'status' => 'UNAVAILABLE',
                'label' => 'Status unavailable',
                'message' => 'Payment status could not be checked. Review submission remains locked; refresh the page or try again shortly.',
            ];
        }

        if ($result->paid) {
            return [
                'confirmed' => true,
                'status' => $result->providerStatus,
                'label' => 'Payment confirmed',
                'message' => 'IDontPay99 confirmed this hosting payment and matched it to this election.',
            ];
        }

        $failed = in_array(strtoupper($result->providerStatus), ['FAILED', 'CANCELLED', 'REFUNDED'], true);

        return [
            'confirmed' => false,
            'status' => $result->providerStatus,
            'label' => $failed ? 'Payment unsuccessful' : 'Awaiting payment',
            'message' => $failed
                ? 'The previous payment was not successful. Retry the hosting-fee checkout before continuing.'
                : 'No confirmed hosting payment was found. Complete checkout, then return here to continue.',
        ];
    }

    /**
     * Stores only the import summary. User/Voter owns the roster rows and has
     * already processed them through its API before this method is called.
     *
     * @param  array{requested:int,assigned:int,skipped:int,removed:int,totalAssigned:int,unmatchedCount:int,unmatchedRows:list<array{row:int,reason:string}>}  $summary
     */
    public function recordPrivateRosterImport(User $user, int $electionId, array $summary): Election
    {
        return DB::transaction(function () use ($user, $electionId, $summary): Election {
            $election = Election::query()->lockForUpdate()->find($electionId);
            if (! $election instanceof Election) {
                throw (new ModelNotFoundException)->setModel(Election::class, [$electionId]);
            }
            $this->assertOwnedBy($user, $election);
            $this->assertPrivateRosterEditable($election);
            if (EligibilityRules::fromStored($election->eligibility_rules)->mode() !== EligibilityRules::PRIVATE) {
                throw new BusinessRuleException('Only a private election can receive a voter list.');
            }

            $expectedVersion = $this->assertExpectedVersion($election, $election->version);
            $election->forceFill([
                'private_voter_list_uploaded_at' => $this->clock->now(),
                'private_voter_count' => $summary['totalAssigned'],
                'private_voter_unmatched_count' => $summary['unmatchedCount'],
            ]);
            $requestId = $this->newRequestId();
            $this->appendTransition($election, [
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'action' => 'import_private_voter_list',
                'from_state' => $election->status,
                'to_state' => $election->status,
                'metadata' => [
                    'requested' => $summary['requested'],
                    'assigned' => $summary['assigned'],
                    'skipped' => $summary['skipped'],
                    'unmatched' => $summary['unmatchedCount'],
                ],
                'created_at' => $this->clock->now()->toDateTimeString(),
            ]);
            $this->persistWithVersion($election, $expectedVersion);
            $this->auditLogger->log('private_voter_list_imported', [
                'election_id' => $electionId,
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'assigned' => $summary['assigned'],
                'skipped' => $summary['skipped'],
                'unmatched' => $summary['unmatchedCount'],
            ]);

            return $election->refresh();
        });
    }

    /**
     * Records only a private-voter directory selection result. The User/Voter
     * module owns the actual enrolment and identity data.
     *
     * @param  array{userId:int,assigned:bool,changed:bool,reasonCode:string,totalAssigned:int}  $selection
     */
    public function recordPrivateVoterSelection(User $user, int $electionId, array $selection): Election
    {
        return DB::transaction(function () use ($user, $electionId, $selection): Election {
            $election = Election::query()->lockForUpdate()->find($electionId);
            if (! $election instanceof Election) {
                throw (new ModelNotFoundException)->setModel(Election::class, [$electionId]);
            }
            $this->assertOwnedBy($user, $election);
            $this->assertPrivateRosterEditable($election);
            if (EligibilityRules::fromStored($election->eligibility_rules)->mode() !== EligibilityRules::PRIVATE) {
                throw new BusinessRuleException('Only a private election can receive voter assignments.');
            }

            $expectedVersion = $this->assertExpectedVersion($election, $election->version);
            $election->forceFill([
                'private_voter_list_uploaded_at' => $this->clock->now(),
                'private_voter_count' => $selection['totalAssigned'],
            ]);
            $requestId = $this->newRequestId();
            $action = $selection['assigned'] ? 'assign_private_voter' : 'remove_private_voter';
            $this->appendTransition($election, [
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'action' => $action,
                'from_state' => $election->status,
                'to_state' => $election->status,
                'metadata' => [
                    'result' => $selection['reasonCode'],
                    'changed' => $selection['changed'],
                    'total_assigned' => $selection['totalAssigned'],
                ],
                'created_at' => $this->clock->now()->toDateTimeString(),
            ]);
            $this->persistWithVersion($election, $expectedVersion);
            $this->auditLogger->log($action, [
                'election_id' => $electionId,
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'changed' => $selection['changed'],
                'total_assigned' => $selection['totalAssigned'],
            ]);

            return $election->refresh();
        });
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
            'positionSought' => (string) $election->position_sought,
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
                    'positionSought' => (string) $election->position_sought,
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

    /**
     * Published elections that remain useful in the participant workspace.
     * Unlike availableForVoting(), this catalog includes the nomination stage
     * so members who register after approval can discover upcoming public
     * elections before voting opens. It also retains an open election after
     * its scheduled end and retains closed elections for historical access.
     *
     * @return list<array<string, mixed>>
     */
    public function voterElectionCatalog(?CarbonImmutable $at = null): array
    {
        $now = $at ?? $this->clock->now();

        return Election::query()
            ->whereIn('status', [
                ElectionStatus::NOMINATION_OPEN->value,
                ElectionStatus::VOTING_OPEN->value,
                ElectionStatus::CLOSED->value,
            ])
            ->withCount([
                'candidacies as approved_ballot_count' => static fn ($query) => $query
                    ->where('status', self::CANDIDACY_APPROVED),
            ])
            ->get()
            ->sort(function (Election $left, Election $right): int {
                $leftIsClosed = $left->status === ElectionStatus::CLOSED->value;
                $rightIsClosed = $right->status === ElectionStatus::CLOSED->value;

                if ($leftIsClosed !== $rightIsClosed) {
                    return $leftIsClosed ? 1 : -1;
                }

                $leftEnd = $left->voting_end?->getTimestamp();
                $rightEnd = $right->voting_end?->getTimestamp();
                if ($leftEnd !== $rightEnd) {
                    if ($leftEnd === null) {
                        return 1;
                    }
                    if ($rightEnd === null) {
                        return -1;
                    }

                    return $leftIsClosed
                        ? $rightEnd <=> $leftEnd
                        : $leftEnd <=> $rightEnd;
                }

                return (int) $left->getKey() <=> (int) $right->getKey();
            })
            ->values()
            ->map(function (Election $election) use ($now): array {
                $votingAllowed = false;
                if ($election->status === ElectionStatus::VOTING_OPEN->value) {
                    try {
                        $votingAllowed = $this->stateFactory
                            ->make(ElectionStatus::VOTING_OPEN->value)
                            ->canVote($election, $now);
                    } catch (BusinessRuleException) {
                        $votingAllowed = false;
                    }
                }

                return [
                    'electionId' => (int) $election->getKey(),
                    'title' => (string) $election->title,
                    'positionSought' => (string) $election->position_sought,
                    'description' => (string) $election->description,
                    'lifecycleState' => (string) $election->status,
                    'votingAllowed' => $votingAllowed,
                    'eligibility' => EligibilityRules::fromStored($election->eligibility_rules)->toArray(),
                    'nominationPeriod' => [
                        'start' => $election->nomination_start?->toAtomString(),
                        'end' => $election->nomination_end?->toAtomString(),
                    ],
                    'votingPeriod' => [
                        'start' => $election->voting_start?->toAtomString(),
                        'end' => $election->voting_end?->toAtomString(),
                    ],
                    'ballotCandidateCount' => (int) $election->getAttribute('approved_ballot_count'),
                ];
            })
            ->all();
    }

    /** @return Collection<int, Candidacy> */
    public function candidateApplications(Election $election): Collection
    {
        return Candidacy::query()
            ->with('user')
            ->where('election_id', $election->getKey())
            ->orderBy('registered_at')
            ->get();
    }

    public function canManageParticipants(Election $election): bool
    {
        return in_array($election->status, [
            ElectionStatus::DRAFT->value,
            ElectionStatus::PENDING_PAYMENT->value,
            ElectionStatus::PENDING_ADMIN_APPROVAL->value,
            ElectionStatus::NOMINATION_OPEN->value,
        ], true);
    }

    /**
     * @return array{users:list<array{userId:int,fullName:string,identityNumber:string,email:string,secondaryText:string,assigned:bool}>,pagination:array{page:int,perPage:int,total:int,hasMore:bool}}
     */
    public function privateCandidateDirectory(User $user, Election $election, string $search, int $page = 1): array
    {
        $this->assertOwnedBy($user, $election);
        $this->assertPrivateCandidatesEditable($election);

        $search = trim($search);
        $identitySearch = Identity::normalisePassportNumber($search);
        $users = User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_admin', false)
            ->where('account_status', 'ACTIVE')
            ->whereNull('account_disabled_at')
            ->where('is_verified', true)
            ->whereNotNull('verified_at')
            ->when($search !== '', function ($query) use ($search, $identitySearch): void {
                $query->where(function ($memberQuery) use ($search, $identitySearch): void {
                    $memberQuery
                        ->where('full_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('ic_number', 'like', '%'.$identitySearch.'%')
                        ->orWhere('passport_number', 'like', '%'.$identitySearch.'%');
                });
            })
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate(25, ['id', 'full_name', 'email', 'ic_number', 'passport_number'], 'page', max(1, $page));

        $assignedIds = Candidacy::query()
            ->where('election_id', $election->getKey())
            ->where('status', self::CANDIDACY_APPROVED)
            ->whereIn('user_id', $users->getCollection()->pluck('id'))
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->flip();

        return [
            'users' => $users->getCollection()
                ->map(static function (User $candidate) use ($assignedIds): array {
                    $identity = (string) ($candidate->ic_number ?: $candidate->passport_number);

                    return [
                        'userId' => (int) $candidate->getKey(),
                        'fullName' => (string) $candidate->display_name,
                        'identityNumber' => $identity,
                        'email' => (string) $candidate->email,
                        'secondaryText' => trim((string) $candidate->email.($identity !== '' ? ' · '.$identity : '')),
                        'assigned' => $assignedIds->has((int) $candidate->getKey()),
                    ];
                })
                ->values()
                ->all(),
            'pagination' => [
                'page' => $users->currentPage(),
                'perPage' => $users->perPage(),
                'total' => $users->total(),
                'hasMore' => $users->hasMorePages(),
            ],
        ];
    }

    /** @return array{userId:int,assigned:bool,changed:bool,reasonCode:string,totalAssigned:int,version:int} */
    public function setPrivateCandidateSelection(
        User $user,
        int $electionId,
        int $candidateUserId,
        bool $assigned,
        string $currentPassword,
    ): array {
        $this->reauthenticateOrganizer(
            $user,
            $electionId,
            $currentPassword,
            $assigned ? 'assign_private_candidate' : 'remove_private_candidate',
        );

        return DB::transaction(function () use ($user, $electionId, $candidateUserId, $assigned): array {
            $election = Election::query()->lockForUpdate()->findOrFail($electionId);
            $this->assertOwnedBy($user, $election);
            $this->assertPrivateCandidatesEditable($election);

            $candidate = User::query()->lockForUpdate()->find($candidateUserId);
            $application = Candidacy::query()
                ->where('election_id', $electionId)
                ->where('user_id', $candidateUserId)
                ->lockForUpdate()
                ->first();

            if ($assigned && (! $candidate instanceof User
                || $candidate->isAdmin()
                || ! $candidate->isActive()
                || ! $candidate->isVerified()
                || $candidate->is($user))) {
                throw new BusinessRuleException('This member is no longer available for candidate assignment.');
            }

            $changed = false;
            $reasonCode = $assigned ? 'ALREADY_ASSIGNED' : 'NOT_ASSIGNED';
            if ($assigned && (! $application instanceof Candidacy || $application->status !== self::CANDIDACY_APPROVED)) {
                $application ??= new Candidacy([
                    'election_id' => $electionId,
                    'user_id' => $candidateUserId,
                    'registered_at' => $this->clock->now(),
                ]);
                $application->status = self::CANDIDACY_APPROVED;
                $application->save();
                $changed = true;
                $reasonCode = 'ASSIGNED';
            } elseif (! $assigned && $application instanceof Candidacy
                && $application->status === self::CANDIDACY_APPROVED) {
                $application->status = self::CANDIDACY_REJECTED;
                $application->save();
                $changed = true;
                $reasonCode = 'REMOVED';
            }

            $totalAssigned = Candidacy::query()
                ->where('election_id', $electionId)
                ->where('status', self::CANDIDACY_APPROVED)
                ->count();

            if ($changed) {
                $expectedVersion = $this->assertExpectedVersion($election, $election->version);
                $requestId = $this->newRequestId();
                $action = $assigned ? 'assign_private_candidate' : 'remove_private_candidate';
                $this->appendTransition($election, [
                    'actor_user_id' => $user->getKey(),
                    'request_id' => $requestId,
                    'action' => $action,
                    'from_state' => $election->status,
                    'to_state' => $election->status,
                    'metadata' => [
                        'candidate_user_id' => $candidateUserId,
                        'result' => $reasonCode,
                        'total_assigned' => $totalAssigned,
                    ],
                    'created_at' => $this->clock->now()->toDateTimeString(),
                ]);
                $this->persistWithVersion($election, $expectedVersion);
                $this->auditLogger->log($action, [
                    'election_id' => $electionId,
                    'actor_user_id' => $user->getKey(),
                    'candidate_user_id' => $candidateUserId,
                    'request_id' => $requestId,
                    'total_assigned' => $totalAssigned,
                ]);
            }

            return [
                'userId' => $candidateUserId,
                'assigned' => $assigned && $application instanceof Candidacy
                    && $application->status === self::CANDIDACY_APPROVED,
                'changed' => $changed,
                'reasonCode' => $reasonCode,
                'totalAssigned' => $totalAssigned,
                'version' => (int) $election->version,
            ];
        });
    }

    /**
     * @param  list<array{row:int,fullName:string,identityNumber:string}>  $rows
     * @return array{requested:int,assigned:int,skipped:int,totalAssigned:int,unmatchedCount:int,unmatchedRows:list<array{row:int,reason:string}>}
     */
    public function importPrivateCandidates(User $user, int $electionId, array $rows, string $currentPassword): array
    {
        $this->reauthenticateOrganizer($user, $electionId, $currentPassword, 'import_private_candidate_list');

        return DB::transaction(function () use ($user, $electionId, $rows): array {
            $election = Election::query()->lockForUpdate()->findOrFail($electionId);
            $this->assertOwnedBy($user, $election);
            $this->assertPrivateCandidatesEditable($election);

            $normalisedRows = [];
            $unmatchedRows = [];
            $seen = [];
            foreach ($rows as $row) {
                $rowNumber = (int) ($row['row'] ?? 0);
                $fullName = Str::lower(Str::squish((string) ($row['fullName'] ?? '')));
                $identityNumber = $this->normaliseParticipantIdentity((string) ($row['identityNumber'] ?? ''));
                $key = $fullName.'|'.$identityNumber;
                if ($rowNumber < 2 || $fullName === '' || $identityNumber === null) {
                    $unmatchedRows[] = ['row' => max(2, $rowNumber), 'reason' => 'INVALID_IDENTITY_NUMBER'];

                    continue;
                }
                if (isset($seen[$key])) {
                    $unmatchedRows[] = ['row' => $rowNumber, 'reason' => 'DUPLICATE_ROW'];

                    continue;
                }
                $seen[$key] = true;
                $normalisedRows[] = ['row' => $rowNumber, 'fullName' => $fullName, 'identityNumber' => $identityNumber];
            }

            $candidates = $this->usersForParticipantIdentities(array_column($normalisedRows, 'identityNumber'));
            $matchedIds = [];
            foreach ($normalisedRows as $row) {
                $matches = $this->usersMatchingParticipantIdentity($candidates, $row['identityNumber']);
                if ($matches->isEmpty()) {
                    $unmatchedRows[] = ['row' => $row['row'], 'reason' => 'NO_MATCHING_ACCOUNT'];

                    continue;
                }
                if ($matches->count() > 1) {
                    $unmatchedRows[] = ['row' => $row['row'], 'reason' => 'IDENTITY_NUMBER_AMBIGUOUS'];

                    continue;
                }

                $candidate = $matches->first();
                if (Str::lower(Str::squish((string) $candidate->full_name)) !== $row['fullName']) {
                    $unmatchedRows[] = ['row' => $row['row'], 'reason' => 'NAME_DOES_NOT_MATCH_IDENTIFIER'];

                    continue;
                }
                if ($candidate->isAdmin() || ! $candidate->isActive() || ! $candidate->isVerified() || $candidate->is($user)) {
                    $unmatchedRows[] = ['row' => $row['row'], 'reason' => 'ACCOUNT_NOT_ELIGIBLE'];

                    continue;
                }
                $matchedIds[] = (int) $candidate->getKey();
            }

            $assigned = 0;
            $skipped = 0;
            foreach (array_values(array_unique($matchedIds)) as $candidateUserId) {
                $application = Candidacy::query()->firstOrNew([
                    'election_id' => $electionId,
                    'user_id' => $candidateUserId,
                ]);
                if ($application->exists && $application->status === self::CANDIDACY_APPROVED) {
                    $skipped++;

                    continue;
                }
                $application->status = self::CANDIDACY_APPROVED;
                if (! $application->exists) {
                    $application->registered_at = $this->clock->now();
                }
                $application->save();
                $assigned++;
            }

            $totalAssigned = Candidacy::query()
                ->where('election_id', $electionId)
                ->where('status', self::CANDIDACY_APPROVED)
                ->count();
            $expectedVersion = $this->assertExpectedVersion($election, $election->version);
            $requestId = $this->newRequestId();
            $this->appendTransition($election, [
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'action' => 'import_private_candidate_list',
                'from_state' => $election->status,
                'to_state' => $election->status,
                'metadata' => [
                    'requested' => count($rows),
                    'assigned' => $assigned,
                    'skipped' => $skipped,
                    'unmatched' => count($unmatchedRows),
                    'total_assigned' => $totalAssigned,
                ],
                'created_at' => $this->clock->now()->toDateTimeString(),
            ]);
            $this->persistWithVersion($election, $expectedVersion);
            $this->auditLogger->log('private_candidate_list_imported', [
                'election_id' => $electionId,
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'assigned' => $assigned,
                'skipped' => $skipped,
                'unmatched' => count($unmatchedRows),
            ]);

            return [
                'requested' => count($rows),
                'assigned' => $assigned,
                'skipped' => $skipped,
                'totalAssigned' => $totalAssigned,
                'unmatchedCount' => count($unmatchedRows),
                'unmatchedRows' => array_slice($unmatchedRows, 0, 100),
            ];
        });
    }

    public function assignPrivateCandidate(
        User $user,
        int $electionId,
        int $candidateUserId,
        string $currentPassword,
        ?int $submittedVersion = null,
    ): Candidacy {
        $this->reauthenticateOrganizer($user, $electionId, $currentPassword, 'assign_candidate');

        return DB::transaction(function () use ($user, $electionId, $candidateUserId, $submittedVersion): Candidacy {
            $election = Election::query()->lockForUpdate()->findOrFail($electionId);
            $this->assertOwnedBy($user, $election);
            if (EligibilityRules::fromStored($election->eligibility_rules)->mode() !== EligibilityRules::PRIVATE) {
                throw new BusinessRuleException('Candidates can be assigned directly only for private elections.');
            }
            if (! $this->canManageParticipants($election)) {
                throw new BusinessRuleException('Private candidates can be assigned until voting begins.');
            }

            $candidate = User::query()->lockForUpdate()->find($candidateUserId);
            if (! $candidate instanceof User
                || $candidate->isAdmin()
                || ! $candidate->isActive()
                || ! $candidate->isVerified()
                || $candidate->is($user)) {
                throw new BusinessRuleException('Select an active, verified member who is eligible for candidate assignment.');
            }
            if (Candidacy::query()
                ->where('election_id', $electionId)
                ->where('user_id', $candidateUserId)
                ->exists()) {
                throw new BusinessRuleException('That member is already assigned to this election.');
            }

            $expectedVersion = $this->assertExpectedVersion($election, $submittedVersion);
            $candidacy = Candidacy::query()->create([
                'user_id' => $candidateUserId,
                'election_id' => $electionId,
                'status' => self::CANDIDACY_APPROVED,
                'registered_at' => $this->clock->now(),
            ]);
            $requestId = $this->newRequestId();
            $this->appendTransition($election, [
                'actor_user_id' => $user->getKey(),
                'request_id' => $requestId,
                'action' => 'assign_candidate',
                'from_state' => $election->status,
                'to_state' => $election->status,
                'metadata' => [
                    'candidacy_id' => $candidacy->getKey(),
                    'candidate_user_id' => $candidateUserId,
                    'status' => self::CANDIDACY_APPROVED,
                ],
                'created_at' => $this->clock->now()->toDateTimeString(),
            ]);
            $this->persistWithVersion($election, $expectedVersion);
            $this->auditLogger->log('private_candidate_assigned', [
                'election_id' => $electionId,
                'actor_user_id' => $user->getKey(),
                'candidate_user_id' => $candidateUserId,
                'request_id' => $requestId,
            ]);

            return $candidacy->load('user');
        });
    }

    /** @return list<array{id:int,candidateId:int,name:string,position:string,manifesto:string}> */
    private function approvedBallot(Election $election): array
    {
        return Candidacy::query()
            ->with('user')
            ->where('election_id', $election->getKey())
            ->where('status', self::CANDIDACY_APPROVED)
            ->orderBy('id')
            ->get()
            ->map(static function (Candidacy $candidacy) use ($election): array {
                $candidate = $candidacy->user;
                $name = trim((string) $candidate?->display_name);

                return [
                    'id' => (int) $candidacy->getKey(),
                    'candidateId' => (int) $candidacy->user_id,
                    'name' => $name !== '' ? $name : 'Candidate '.$candidacy->user_id,
                    'position' => (string) $election->position_sought,
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
        $rules = $mode === EligibilityRules::PRIVATE
            ? EligibilityRules::privateElection()
            : EligibilityRules::publicElection(
                ($input['eligibility_minimum_age'] ?? '') === '' ? null : (int) $input['eligibility_minimum_age'],
                strtoupper((string) ($input['eligibility_nationality'] ?? 'ANY')),
            );

        return [
            'title' => trim((string) $input['title']),
            'position_sought' => trim((string) $input['position_sought']),
            'description' => trim((string) $input['description']),
            'eligibility_rules' => $rules->toStorage(),
            'nomination_start' => CarbonImmutable::parse($input['nomination_start'])->toDateTimeString(),
            'nomination_end' => CarbonImmutable::parse($input['nomination_end'])->toDateTimeString(),
            'voting_start' => CarbonImmutable::parse($input['voting_start'])->toDateTimeString(),
            'voting_end' => CarbonImmutable::parse($input['voting_end'])->toDateTimeString(),
            'hosting_fee' => Election::hostingFeeForMode($rules->mode()),
            'currency' => strtoupper((string) config('election.hosting_fee.currency', 'MYR')),
        ];
    }

    /** @param array<string, mixed> $record */
    private function appendTransition(Election $election, array $record): void
    {
        $record['type'] ??= ($record['from_state'] ?? null) === ($record['to_state'] ?? null)
            ? ElectionTransitionRecord::DOMAIN_EVENT
            : ElectionTransitionRecord::LIFECYCLE_TRANSITION;

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

    private function assertPrivateRosterEditable(Election $election): void
    {
        if (! $this->canManageParticipants($election)) {
            throw new BusinessRuleException('Private voters can be managed until voting begins.');
        }
    }

    private function assertPrivateCandidatesEditable(Election $election): void
    {
        if (EligibilityRules::fromStored($election->eligibility_rules)->mode() !== EligibilityRules::PRIVATE) {
            throw new BusinessRuleException('Only private elections can receive direct candidate assignments.');
        }
        if (! $this->canManageParticipants($election)) {
            throw new BusinessRuleException('Private candidates can be managed until voting begins.');
        }
    }

    /** @param list<string> $identityNumbers
     * @return Collection<int, User> */
    private function usersForParticipantIdentities(array $identityNumbers): Collection
    {
        $icNumbers = array_values(array_unique(array_filter(
            $identityNumbers,
            static fn (string $number): bool => Identity::isValidIcNumber($number),
        )));
        $passportNumbers = array_values(array_unique(array_filter(
            array_map(static fn (string $number): string => Identity::normalisePassportNumber($number), $identityNumbers),
            static fn (string $number): bool => Identity::isValidPassportNumber($number),
        )));

        if ($icNumbers === [] && $passportNumbers === []) {
            return new Collection;
        }

        return User::query()
            ->where(function ($query) use ($icNumbers, $passportNumbers): void {
                if ($icNumbers !== []) {
                    $query->whereIn('ic_number', $icNumbers);
                }
                if ($passportNumbers !== []) {
                    $method = $icNumbers === [] ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('passport_number', $passportNumbers);
                }
            })
            ->get();
    }

    /** @param Collection<int, User> $users
     * @return Collection<int, User> */
    private function usersMatchingParticipantIdentity(Collection $users, string $identityNumber): Collection
    {
        return $users->filter(function (User $candidate) use ($identityNumber): bool {
            $icNumber = Identity::normaliseIcNumber((string) $candidate->ic_number);
            $passportNumber = Identity::normalisePassportNumber((string) $candidate->passport_number);

            return ($icNumber !== '' && hash_equals($icNumber, $identityNumber))
                || ($passportNumber !== '' && hash_equals($passportNumber, $identityNumber));
        });
    }

    private function normaliseParticipantIdentity(string $identityNumber): ?string
    {
        $icNumber = Identity::normaliseIcNumber($identityNumber);
        if (Identity::isValidIcNumber($icNumber)) {
            return $icNumber;
        }

        $passportNumber = Identity::normalisePassportNumber($identityNumber);

        return Identity::isValidPassportNumber($passportNumber) ? $passportNumber : null;
    }

    private function newRequestId(): string
    {
        return 'EL-'.strtoupper(bin2hex(random_bytes(12)));
    }
}
