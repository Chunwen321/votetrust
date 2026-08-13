<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;
use Carbon\CarbonImmutable;

final class VotingOpenState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::VOTING_OPEN->value;
    }

    public function label(): string
    {
        return 'Voting open';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'close_voting', 'label' => 'Close election', 'description' => 'Available after the configured voting end.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'cancel', 'label' => 'Emergency cancellation', 'description' => 'Cancels an active election.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function canVote(Election $election, CarbonImmutable $now): bool
    {
        return $this->paymentVerified($election)
            && $this->adminApproved($election)
            && $now->betweenIncluded($this->date($election, 'voting_start'), $this->date($election, 'voting_end'));
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action !== 'close_voting') {
            $this->reject($action);
        }
        if ($context->now->lt($this->date($lifecycle->election(), 'voting_end'))) {
            throw new BusinessRuleException('The election cannot close before the configured voting end time.');
        }
        $lifecycle->moveTo(ElectionStatus::CLOSED->value, $action);
    }
}
