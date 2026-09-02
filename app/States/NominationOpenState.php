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
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Cancels the published election.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function canRegisterCandidate(Election $election, CarbonImmutable $now): bool
    {
        return $this->paymentVerified($election)
            && $now->betweenIncluded($this->date($election, 'nomination_start'), $this->date($election, 'nomination_end'));
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action !== 'close_nomination') {
            $this->reject($action);
        }

        $election = $lifecycle->election();
        if ($context->now->lt($this->date($election, 'nomination_end'))) {
            throw new BusinessRuleException('Nominations cannot close before the configured nomination end time.');
        }

        $lifecycle->moveTo(ElectionStatus::NOMINATION_CLOSED->value, $action);
    }
}
