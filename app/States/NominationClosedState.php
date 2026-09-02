<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;

final class NominationClosedState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::NOMINATION_CLOSED->value;
    }

    public function label(): string
    {
        return 'Nominations closed';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Cancels the election before voting begins.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action !== 'start_voting') {
            $this->reject($action);
        }

        $election = $lifecycle->election();
        if (! $this->paymentVerified($election)) {
            throw new BusinessRuleException('Voting requires confirmed payment.');
        }
        if ((int) $context->input('approved_candidate_count', 0) < 1) {
            throw new BusinessRuleException('Voting cannot open until at least one candidate is approved.');
        }
        if ((int) $context->input('pending_candidate_count', 0) > 0) {
            throw new BusinessRuleException('Review every pending candidate application before voting can open.');
        }
        if ($context->now->lt($this->date($election, 'voting_start'))) {
            throw new BusinessRuleException('Voting cannot open before the configured voting start time.');
        }

        $lifecycle->moveTo(ElectionStatus::VOTING_OPEN->value, $action);
    }
}
