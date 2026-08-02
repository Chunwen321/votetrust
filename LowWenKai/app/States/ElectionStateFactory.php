<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Models\Election;

final class ElectionStateFactory
{
    public function make(string $state): ElectionState
    {
        return match ($state) {
            Election::DRAFT => new DraftState(),
            Election::PENDING_PAYMENT => new PendingPaymentState(),
            Election::PENDING_ADMIN_APPROVAL => new PendingAdminApprovalState(),
            Election::NOMINATION_OPEN => new NominationOpenState(),
            Election::VOTING_OPEN => new VotingOpenState(),
            Election::CLOSED => new ClosedState(),
            Election::CANCELLED => new CancelledState(),
            Election::REJECTED => new RejectedState(),
            default => throw new BusinessRuleException("Unknown election lifecycle state: {$state}"),
        };
    }
}
