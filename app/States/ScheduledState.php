<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;

final class ScheduledState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::SCHEDULED->value;
    }

    public function label(): string
    {
        return 'Approved and scheduled';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'start_nomination', 'label' => 'Start nominations', 'description' => 'Available when the configured nomination start time is reached. This is the final scheduled-stage action that requires your password.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Cancels this approved election before nominations begin.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($action === 'expire_schedule') {
            if ($this->expireOverduePublicElection(
                $lifecycle,
                $context,
                (string) $context->input('expiry_trigger', 'scheduler'),
            )) {
                return;
            }

            throw new BusinessRuleException('Only a public scheduled election whose nomination period has ended can expire.');
        }

        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action !== 'start_nomination') {
            $this->reject($action);
        }

        $election = $lifecycle->election();
        if (! $this->paymentVerified($election) || ! $this->adminApproved($election)) {
            throw new BusinessRuleException('Nominations require confirmed payment and administrator approval.');
        }
        if ($context->now->lt($this->date($election, 'nomination_start'))) {
            throw new BusinessRuleException('Nominations cannot start before the configured nomination start time.');
        }
        if ($context->now->gte($this->date($election, 'nomination_end'))) {
            throw new BusinessRuleException('The configured nomination period has already ended.');
        }

        $lifecycle->moveTo(ElectionStatus::NOMINATION_OPEN->value, $action);
    }
}
