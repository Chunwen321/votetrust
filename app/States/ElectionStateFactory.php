<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;

final class ElectionStateFactory
{
    public function make(string $state): ElectionState
    {
        return match ($state) {
            ElectionStatus::DRAFT->value => new DraftState,
            ElectionStatus::PENDING_FEE_QUOTE->value => new PendingFeeQuoteState,
            ElectionStatus::PENDING_FEE_ACCEPTANCE->value => new PendingFeeAcceptanceState,
            ElectionStatus::PENDING_PAYMENT->value => new PendingPaymentState,
            ElectionStatus::PENDING_ADMIN_APPROVAL->value => new PendingAdminApprovalState,
            ElectionStatus::NOMINATION_OPEN->value => new NominationOpenState,
            ElectionStatus::VOTING_OPEN->value => new VotingOpenState,
            ElectionStatus::CLOSED->value => new ClosedState,
            ElectionStatus::CANCELLED->value => new CancelledState,
            ElectionStatus::REJECTED->value => new RejectedState,
            default => throw new BusinessRuleException("Unknown election lifecycle state: {$state}"),
        };
    }
}
