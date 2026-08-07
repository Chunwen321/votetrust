<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Services;

use VoteTrust\UserAdmin\Exceptions\NotFoundException;
use VoteTrust\UserAdmin\Exceptions\ValidationException;
use VoteTrust\UserAdmin\Models\AdminRepository;

final class AdminManagementService
{
    /** @var list<string> */
    private const VOTER_STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'SUSPENDED'];

    /** @var list<string> */
    private const CANDIDATE_STATUSES = ['Pending', 'Approved', 'Rejected'];

    public function __construct(private readonly AdminRepository $repository = new AdminRepository())
    {
    }

    /** @return array{elections:int,active:int,voters:int,pending_candidates:int} */
    public function dashboardCounts(): array
    {
        return $this->repository->dashboardCounts();
    }

    /** @return list<array<string, mixed>> */
    public function elections(): array
    {
        return $this->repository->listElections();
    }

    /** @return array<string, mixed> */
    public function election(int $electionId): array
    {
        $election = $this->repository->findElection($electionId);
        if ($election === null) {
            throw new NotFoundException('The election was not found.');
        }

        return $election;
    }

    /** @return list<array<string, mixed>> */
    public function voters(int $electionId): array
    {
        $this->election($electionId);

        return $this->repository->listVoters($electionId);
    }

    /** @return list<array<string, mixed>> */
    public function candidates(int $electionId): array
    {
        $this->election($electionId);

        return $this->repository->listCandidates($electionId);
    }

    /** @return list<array<string, mixed>> */
    public function eligibleUsers(): array
    {
        return $this->repository->listEligibleUsers();
    }

    /** @param array<string, mixed> $input */
    public function createElection(array $admin, array $input): int
    {
        return $this->repository->createElection($this->validatedElectionValues($input), (int) $admin['id']);
    }

    /** @param array<string, mixed> $input */
    public function updateElection(array $admin, int $electionId, array $input): void
    {
        $this->election($electionId);
        $this->repository->updateElection($electionId, $this->validatedElectionValues($input), (int) $admin['id']);
    }

    public function electionAction(array $admin, int $electionId, string $action): void
    {
        $election = $this->election($electionId);
        $statuses = [
            'approve' => 'NOMINATION_OPEN',
            'open_voting' => 'VOTING_OPEN',
            'close' => 'CLOSED',
            'cancel' => 'CANCELLED',
            'restore_draft' => 'DRAFT',
        ];
        $status = $statuses[$action] ?? null;
        if ($status === null) {
            throw new ValidationException(errors: ['action' => 'Choose a valid election action.']);
        }
        if ($status === 'NOMINATION_OPEN' && ($election['nomination_start'] === null || $election['nomination_end'] === null)) {
            throw new ValidationException(errors: ['action' => 'Set nomination start and end times before approving an election.']);
        }
        if ($status === 'VOTING_OPEN' && ($election['voting_start'] === null || $election['voting_end'] === null)) {
            throw new ValidationException(errors: ['action' => 'Set voting start and end times before opening voting.']);
        }

        $this->repository->changeElectionStatus($electionId, $status, (int) $admin['id']);
    }

    public function addVoter(array $admin, int $electionId, int $userId, string $status): void
    {
        $this->election($electionId);
        $this->assertEligibleUser($userId);
        $status = strtoupper(trim($status));
        if (!in_array($status, self::VOTER_STATUSES, true)) {
            throw new ValidationException(errors: ['status' => 'Choose a valid voter status.']);
        }

        $this->repository->addOrUpdateVoter($electionId, $userId, $status, (int) $admin['id']);
    }

    public function voterAction(int $electionId, int $userId, string $status): void
    {
        $this->election($electionId);
        $status = strtoupper(trim($status));
        if (!in_array($status, self::VOTER_STATUSES, true)) {
            throw new ValidationException(errors: ['status' => 'Choose a valid voter status.']);
        }

        $this->repository->changeVoterStatus($electionId, $userId, $status);
    }

    public function addCandidate(int $electionId, int $userId, string $status): void
    {
        $this->election($electionId);
        $this->assertEligibleUser($userId);
        $status = ucfirst(strtolower(trim($status)));
        if (!in_array($status, self::CANDIDATE_STATUSES, true)) {
            throw new ValidationException(errors: ['status' => 'Choose a valid candidate status.']);
        }

        $this->repository->addOrUpdateCandidate($electionId, $userId, $status);
    }

    public function candidateAction(int $electionId, int $userId, string $status): void
    {
        $this->election($electionId);
        $status = ucfirst(strtolower(trim($status)));
        if (!in_array($status, self::CANDIDATE_STATUSES, true)) {
            throw new ValidationException(errors: ['status' => 'Choose a valid candidate status.']);
        }

        $this->repository->changeCandidateStatus($electionId, $userId, $status);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, string|null>
     */
    private function validatedElectionValues(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $rules = trim((string) ($input['eligibility_rules'] ?? ''));
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'MYR')));
        $fee = trim((string) ($input['hosting_fee'] ?? ''));
        $dates = [
            'nomination_start' => $this->dateValue($input['nomination_start'] ?? null),
            'nomination_end' => $this->dateValue($input['nomination_end'] ?? null),
            'voting_start' => $this->dateValue($input['voting_start'] ?? null),
            'voting_end' => $this->dateValue($input['voting_end'] ?? null),
        ];
        $errors = [];
        if ($title === '' || mb_strlen($title) > 150) {
            $errors['title'] = 'Enter an election title of up to 150 characters.';
        }
        if (mb_strlen($description) > 5000) {
            $errors['description'] = 'Description must be at most 5,000 characters.';
        }
        if (mb_strlen($rules) > 5000) {
            $errors['eligibility_rules'] = 'Eligibility rules must be at most 5,000 characters.';
        }
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors['currency'] = 'Use a three-letter currency code.';
        }
        if ($fee !== '' && (!is_numeric($fee) || (float) $fee < 0 || (float) $fee > 99999999.99)) {
            $errors['hosting_fee'] = 'Enter a valid non-negative hosting fee.';
        }
        if (($dates['nomination_start'] === null) !== ($dates['nomination_end'] === null)) {
            $errors['nomination_start'] = 'Provide both nomination dates or leave both blank.';
        }
        if (($dates['voting_start'] === null) !== ($dates['voting_end'] === null)) {
            $errors['voting_start'] = 'Provide both voting dates or leave both blank.';
        }
        if ($dates['nomination_start'] !== null && $dates['nomination_end'] !== null && $dates['nomination_start'] >= $dates['nomination_end']) {
            $errors['nomination_end'] = 'Nomination end must be after nomination start.';
        }
        if ($dates['voting_start'] !== null && $dates['voting_end'] !== null && $dates['voting_start'] >= $dates['voting_end']) {
            $errors['voting_end'] = 'Voting end must be after voting start.';
        }
        if ($dates['nomination_end'] !== null && $dates['voting_start'] !== null && $dates['nomination_end'] > $dates['voting_start']) {
            $errors['voting_start'] = 'Voting must start after nominations end.';
        }
        if ($errors !== []) {
            throw new ValidationException(errors: $errors);
        }

        return [
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'eligibility_rules' => $rules === '' ? null : $rules,
            ...$dates,
            'hosting_fee' => $fee === '' ? null : number_format((float) $fee, 2, '.', ''),
            'currency' => $currency,
        ];
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        if (!$date instanceof \DateTimeImmutable) {
            throw new ValidationException(errors: ['dates' => 'Use valid date and time values.']);
        }

        return $date->format('Y-m-d H:i:s');
    }

    private function assertEligibleUser(int $userId): void
    {
        foreach ($this->repository->listEligibleUsers() as $user) {
            if ((int) $user['id'] === $userId) {
                return;
            }
        }

        throw new ValidationException(errors: ['user_id' => 'Choose an active non-administrator user.']);
    }
}
