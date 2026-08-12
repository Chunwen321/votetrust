<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;
use Carbon\CarbonImmutable;

final class NominationOpenState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::NOMINATION_OPEN->value;
    }

    public function label(): string
    {
        return 'Nominations';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'start_voting', 'label' => 'Open voting', 'description' => 'Requires a complete ballot and the configured voting time.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresHostingFee' => false, 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Cancels the published election.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresHostingFee' => false, 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function canRegisterCandidate(Election $election, CarbonImmutable $now): bool
    {
        return $this->paymentVerified($election)
            && $this->adminApproved($election)
            && $now->betweenIncluded($this->date($election, 'nomination_start'), $this->date($election, 'nomination_end'));
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
        if (! $this->paymentVerified($election) || ! $this->adminApproved($election)) {
            throw new BusinessRuleException('Voting requires confirmed payment and administrator approval.');
        }
        if ((int) $context->input('approved_candidate_count', 0) < 1) {
            throw new BusinessRuleException('Voting cannot open until at least one candidate is approved.');
        }
        if ((int) $context->input('pending_candidate_count', 0) > 0) {
            throw new BusinessRuleException('Review every pending candidate application before opening voting.');
        }
        if ($context->now->lt($this->date($election, 'nomination_end'))
            || $context->now->lt($this->date($election, 'voting_start'))) {
            throw new BusinessRuleException('Voting cannot open before nominations end and voting start is reached.');
        }

        $lifecycle->moveTo(ElectionStatus::VOTING_OPEN->value, $action);
    }
}
