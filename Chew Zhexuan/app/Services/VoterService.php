<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Services;

use VoteTrust\UserAdmin\Exceptions\AuthorizationException;
use VoteTrust\UserAdmin\Exceptions\NotFoundException;
use VoteTrust\UserAdmin\Exceptions\ValidationException;
use VoteTrust\UserAdmin\Models\VoterRepository;

final class VoterService
{
    public function __construct(private readonly VoterRepository $voters = new VoterRepository())
    {
    }

    /** @return list<array<string, mixed>> */
    public function applicationsForVoter(int $voterId): array
    {
        return $this->voters->listForVoter($voterId);
    }

    /** @return list<array<string, mixed>> */
    public function applicationsForAdmin(?string $status): array
    {
        return $this->voters->listForAdmin($status);
    }

    /** @return array<string, int> */
    public function statusCounts(): array
    {
        return $this->voters->statusCounts();
    }

    /** @param array<string, mixed> $admin */
    public function decide(array $admin, int $voterId, int $electionId, string $action): void
    {
        $eligibility = $this->eligibilityRecord($voterId, $electionId);
        if ((int) $admin['id'] === (int) $eligibility['voter_id']) {
            throw new AuthorizationException('Administrators may not decide their own voter eligibility.');
        }

        $actionToStatus = [
            'approve' => 'APPROVED',
            'reject' => 'REJECTED',
            'suspend' => 'SUSPENDED',
        ];
        $status = $actionToStatus[$action] ?? null;
        if ($status === null) {
            throw new ValidationException(errors: ['action' => 'Choose an allowed administrative action.']);
        }

        if ($status === 'APPROVED' && ($eligibility['account_status'] ?? 'ACTIVE') !== 'ACTIVE') {
            throw new ValidationException(errors: ['application' => 'An inactive account cannot be approved for voting.']);
        }

        $this->voters->updateStatus($voterId, $electionId, $status);
    }

    /** @return array<string, mixed> */
    public function eligibility(int $voterId, int $electionId): array
    {
        if (!$this->voters->electionExists($electionId)) {
            throw new NotFoundException('The election does not exist.');
        }
        $eligibility = $this->voters->findEligibility($voterId, $electionId);
        if ($eligibility === null) {
            return ['eligible' => false, 'reasonCode' => 'NO_ELIGIBILITY_RECORD'];
        }
        if ((int) ($eligibility['is_admin'] ?? 0) === 1) {
            return ['eligible' => false, 'reasonCode' => 'ADMIN_ACCOUNT'];
        }
        if (($eligibility['account_status'] ?? 'ACTIVE') !== 'ACTIVE') {
            return ['eligible' => false, 'reasonCode' => 'ACCOUNT_INACTIVE'];
        }
        if (($eligibility['eligibility_status'] ?? 'PENDING') !== 'APPROVED') {
            return ['eligible' => false, 'reasonCode' => 'VOTER_' . (string) $eligibility['eligibility_status']];
        }

        return ['eligible' => true, 'reasonCode' => 'APPROVED'];
    }

    /** @return array<string, mixed> */
    private function eligibilityRecord(int $voterId, int $electionId): array
    {
        $eligibility = $this->voters->findEligibility($voterId, $electionId);
        if ($eligibility === null) {
            throw new NotFoundException('The voter enrolment record was not found.');
        }

        return $eligibility;
    }

}
