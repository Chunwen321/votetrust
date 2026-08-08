<?php

declare(strict_types=1);

namespace VoteTrust\Election\Services;

use DateTimeImmutable;
use PDO;
use VoteTrust\Election\Core\Clock;
use VoteTrust\Election\Core\Database;
use VoteTrust\Election\Core\SystemClock;
use VoteTrust\Election\Exceptions\AuthorizationException;
use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Exceptions\NotFoundException;
use VoteTrust\Election\Models\Candidacy;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Models\User;
use VoteTrust\Election\Security\ReauthenticationGuard;
use VoteTrust\Election\Security\SecurityAuditLogger;
use VoteTrust\Election\Services\Payment\HttpPaymentGateway;
use VoteTrust\Election\Services\Payment\PaymentGateway;
use VoteTrust\Election\States\ElectionLifecycle;
use VoteTrust\Election\States\ElectionStateFactory;
use VoteTrust\Election\Validation\ElectionValidator;

final class ElectionService
{
    private const ADMIN_ACTIONS = ['approve', 'reject'];

    public function __construct(
        private readonly ElectionValidator $validator = new ElectionValidator(),
        private readonly ReauthenticationGuard $reauthentication = new ReauthenticationGuard(),
        private readonly PaymentGateway $paymentGateway = new HttpPaymentGateway(
            'http://127.0.0.1:8081/api/v1/payments/verify',
            'local-development-key'
        ),
        private readonly Clock $clock = new SystemClock(),
        private readonly SecurityAuditLogger $auditLogger = new SecurityAuditLogger(),
        private readonly ElectionStateFactory $stateFactory = new ElectionStateFactory()
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(paymentGateway: HttpPaymentGateway::fromConfig());
    }

    /** @param array<string, mixed> $input */
    public function create(User $organizer, array $input): Election
    {
        $this->assertOrganizer($organizer);
        $valid = $this->validator->validate($input);
        $valid['organizer_id'] = $organizer->id();
        $valid['status'] = Election::DRAFT;
        $valid['version'] = 1;
        $requestId = $this->newRequestId();
        $createdAt = $this->clock->now()->format('Y-m-d H:i:s');
        $valid['transition_history_json'] = json_encode([[
            'actor_user_id' => $organizer->id(),
            'request_id' => $requestId,
            'action' => 'create',
            'from_state' => 'NONE',
            'to_state' => Election::DRAFT,
            'metadata' => [],
            'created_at' => $createdAt,
        ]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $election = Election::create($valid);
        $this->auditLogger->log('election_created', [
            'election_id' => $election->id(),
            'actor_user_id' => $organizer->id(),
            'request_id' => $requestId,
        ]);

        return $election;
    }

    /** @param array<string, mixed> $input */
    public function update(User $organizer, int $electionId, array $input): Election
    {
        $election = $this->findOwned($organizer, $electionId);
        if ($election->status() !== Election::DRAFT) {
            throw new BusinessRuleException('Only a draft election can be edited.');
        }

        $election->fill($this->validator->validate($input));
        $election->save();

        return $election;
    }

    /** @param array<string, mixed> $input */
    public function transition(
        User $actor,
        int $electionId,
        string $action,
        string $currentPassword,
        array $input = []
    ): Election {
        $this->authorizeAction($actor, $electionId, $action);
        $this->reauthentication->assertPassword($actor, $currentPassword, "election:{$electionId}:{$action}");

        return $this->performTransition($actor, $electionId, $action, $input, $this->newRequestId());
    }

    /**
     * Trusted service-to-service entry point. The caller must authenticate the
     * Admin module API key before invoking this method.
     *
     * @param array<string, mixed> $input
     */
    public function transitionFromAdminApi(
        User $admin,
        int $electionId,
        string $action,
        array $input,
        string $requestId
    ): Election {
        if (!in_array($action, self::ADMIN_ACTIONS, true)) {
            throw new AuthorizationException('The Admin API only accepts approval decisions.');
        }
        $this->authorizeAction($admin, $electionId, $action);

        return $this->performTransition($admin, $electionId, $action, $input, $requestId);
    }

    /** @param array<string, mixed> $input */
    private function performTransition(
        User $actor,
        int $electionId,
        string $action,
        array $input,
        string $requestId
    ): Election {
        return Database::transaction(function (PDO $pdo) use ($actor, $electionId, $action, $input, $requestId): Election {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $lock = $pdo->prepare('SELECT id FROM `election` WHERE id = ? FOR UPDATE');
                $lock->execute([$electionId]);
            }

            $election = Election::find($electionId);
            if (!$election instanceof Election) {
                throw new NotFoundException('Election not found.');
            }
            $this->assertActionActor($actor, $election, $action);

            if ($action === 'start_voting') {
                $candidateSummary = $this->candidateSummary($election);
                $input['approved_candidate_count'] = $candidateSummary['approved'];
                $input['pending_candidate_count'] = $candidateSummary['pending'];
            }

            foreach ($election->transitionLogs() as $record) {
                if (hash_equals((string) $record->get('request_id', ''), $requestId)) {
                    return $election;
                }
            }

            $lifecycle = new ElectionLifecycle($election, $this->stateFactory);
            $context = new TransitionContext(
                $actor,
                $this->clock->now(),
                $requestId,
                $this->paymentGateway,
                $this->auditLogger,
                $input
            );
            $lifecycle->transition($action, $context);
            $transition = $lifecycle->lastTransition();
            if ($transition === null) {
                throw new BusinessRuleException('No lifecycle transition was produced.');
            }

            $election->appendTransition([
                'actor_user_id' => $actor->id(),
                'request_id' => $requestId,
                'action' => $transition['action'],
                'from_state' => $transition['from'],
                'to_state' => $transition['to'],
                'metadata' => $transition['metadata'],
                'created_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]);
            $election->save();

            $this->auditLogger->log('election_lifecycle_transition', [
                'election_id' => $election->id(),
                'actor_user_id' => $actor->id(),
                'actor_role' => $actor->role(),
                'request_id' => $requestId,
                'from' => $transition['from'],
                'to' => $transition['to'],
                'action' => $transition['action'],
            ]);

            return $election;
        });
    }

    public function findOwned(User $organizer, int $electionId): Election
    {
        $this->assertOrganizer($organizer);
        $election = Election::find($electionId);
        if (!$election instanceof Election) {
            throw new NotFoundException('Election not found.');
        }
        if (!$election->ownedBy($organizer)) {
            throw new AuthorizationException('Only the organiser who created this election can manage it.');
        }

        return $election;
    }

    public function findForAdmin(User $admin, int $electionId): Election
    {
        $this->assertAdmin($admin);
        $election = Election::find($electionId);
        if (!$election instanceof Election) {
            throw new NotFoundException('Election not found.');
        }

        return $election;
    }

    /** @return list<array{action:string,label:string,description:string,tone:string,audience:string,requiresPaymentReference:bool,requiresReason:bool}> */
    public function actions(Election $election, User $actor): array
    {
        $audience = $actor->isAdmin() ? 'admin' : 'organizer';
        if ($audience === 'organizer' && !$election->ownedBy($actor)) {
            return [];
        }

        return array_values(array_filter(
            $this->stateFactory->make($election->status())->actions($election),
            static fn (array $action): bool => $action['audience'] === $audience
        ));
    }

    /** @return array<string, mixed> */
    public function availability(Election $election, ?DateTimeImmutable $at = null): array
    {
        $now = $at ?? $this->clock->now();
        $state = $this->stateFactory->make($election->status());

        return [
            'electionId' => $election->id(),
            'title' => (string) $election->get('title'),
            'lifecycleState' => $state->name(),
            'lifecycleLabel' => $state->label(),
            'hostingPaymentVerified' => $election->paymentVerified(),
            'administratorApproved' => $election->adminApproved(),
            'eligibility' => $election->eligibilityRules()->toArray(),
            'candidateRegistrationAllowed' => $state->canRegisterCandidate($election, $now),
            'votingAllowed' => $state->canVote($election, $now),
            'nominationPeriod' => [
                'start' => $election->date('nomination_start')->format(DATE_ATOM),
                'end' => $election->date('nomination_end')->format(DATE_ATOM),
            ],
            'votingPeriod' => [
                'start' => $election->date('voting_start')->format(DATE_ATOM),
                'end' => $election->date('voting_end')->format(DATE_ATOM),
            ],
            'checkedAt' => $now->format(DATE_ATOM),
        ];
    }

    /** @return list<Candidacy> */
    public function candidateApplications(Election $election): array
    {
        /** @var list<Candidacy> $applications */
        $applications = Candidacy::where('election_id', $election->id())
            ->orderBy('registered_at', 'ASC')
            ->get();

        return $applications;
    }

    /**
     * Reads the shared candidacy records created by the Candidate Registration
     * Module. This method can later be replaced by that module's API adapter.
     *
     * @return array{applications:list<Candidacy>,total:int,pending:int,approved:int,rejected:int,ready:bool}
     */
    public function candidateSummary(Election $election): array
    {
        $applications = $this->candidateApplications($election);
        $summary = [
            'applications' => $applications,
            'total' => count($applications),
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'ready' => false,
        ];

        foreach ($applications as $application) {
            match ($application->status()) {
                Candidacy::APPROVED => $summary['approved']++,
                Candidacy::REJECTED => $summary['rejected']++,
                default => $summary['pending']++,
            };
        }

        $summary['ready'] = $summary['approved'] > 0 && $summary['pending'] === 0;

        return $summary;
    }

    /**
     * @param array<int|string, mixed> $decisions
     */
    public function reviewCandidates(
        User $organizer,
        int $electionId,
        array $decisions,
        string $currentPassword
    ): int {
        $election = $this->findOwned($organizer, $electionId);
        if ($election->status() !== Election::NOMINATION_OPEN) {
            throw new BusinessRuleException('Candidate decisions are available only while nominations are open.');
        }
        $this->reauthentication->assertPassword(
            $organizer,
            $currentPassword,
            "election:{$electionId}:candidate_review"
        );

        $allowedStatuses = [Candidacy::PENDING, Candidacy::APPROVED, Candidacy::REJECTED];
        $requestId = $this->newRequestId();

        return Database::transaction(function (PDO $pdo) use (
            $organizer,
            $electionId,
            $decisions,
            $allowedStatuses,
            $requestId
        ): int {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $electionLock = $pdo->prepare('SELECT id FROM `election` WHERE id = ? FOR UPDATE');
                $electionLock->execute([$electionId]);
                $candidateLock = $pdo->prepare('SELECT id FROM `candidacy` WHERE election_id = ? FOR UPDATE');
                $candidateLock->execute([$electionId]);
            }

            $election = Election::find($electionId);
            if (!$election instanceof Election || !$election->ownedBy($organizer)) {
                throw new AuthorizationException('Only the owning organiser can review these candidates.');
            }
            if ($election->status() !== Election::NOMINATION_OPEN) {
                throw new BusinessRuleException('Candidate decisions are available only while nominations are open.');
            }

            $applications = $this->candidateApplications($election);
            $applicationsById = [];
            foreach ($applications as $application) {
                $applicationsById[$application->id()] = $application;
            }

            $changes = [];
            foreach ($decisions as $applicationId => $decision) {
                $id = filter_var($applicationId, FILTER_VALIDATE_INT);
                $status = trim((string) $decision);
                if ($id === false || !isset($applicationsById[$id])) {
                    throw new BusinessRuleException('A candidate decision does not belong to this election.');
                }
                if (!in_array($status, $allowedStatuses, true)) {
                    throw new BusinessRuleException('Select a valid candidate decision.');
                }

                $application = $applicationsById[$id];
                if ($application->status() === $status) {
                    continue;
                }

                $application->set('status', $status);
                $application->save();
                $changes[] = [
                    'candidacy_id' => $application->id(),
                    'candidate_id' => (int) $application->get('candidate_id'),
                    'decision' => $status,
                ];
            }

            if ($changes === []) {
                return 0;
            }

            $election->appendTransition([
                'actor_user_id' => $organizer->id(),
                'request_id' => $requestId,
                'action' => 'review_candidates',
                'from_state' => Election::NOMINATION_OPEN,
                'to_state' => Election::NOMINATION_OPEN,
                'metadata' => ['candidate_decisions' => $changes],
                'created_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]);
            $election->save();

            $this->auditLogger->log('candidate_ballot_decisions_updated', [
                'election_id' => $electionId,
                'actor_user_id' => $organizer->id(),
                'request_id' => $requestId,
                'candidate_decisions' => $changes,
            ]);

            return count($changes);
        });
    }

    private function authorizeAction(User $actor, int $electionId, string $action): void
    {
        $election = Election::find($electionId);
        if (!$election instanceof Election) {
            throw new NotFoundException('Election not found.');
        }
        $this->assertActionActor($actor, $election, $action);
    }

    private function assertActionActor(User $actor, Election $election, string $action): void
    {
        if (in_array($action, self::ADMIN_ACTIONS, true)) {
            $this->assertAdmin($actor);
            if ($election->ownedBy($actor)) {
                throw new AuthorizationException('An administrator cannot approve their own election.');
            }

            return;
        }

        $this->assertOrganizer($actor);
        if (!$election->ownedBy($actor)) {
            throw new AuthorizationException('Only the owning organiser can perform this action.');
        }
    }

    private function assertOrganizer(User $user): void
    {
        if (!$user->isOrganizer() || $user->isAdmin()) {
            throw new AuthorizationException('An organiser account is required.');
        }
    }

    private function assertAdmin(User $user): void
    {
        if (!$user->isAdmin()) {
            throw new AuthorizationException('A system administrator account is required.');
        }
    }

    private function newRequestId(): string
    {
        return 'EL-' . strtoupper(bin2hex(random_bytes(12)));
    }
}
