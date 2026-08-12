<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Models\Election;
use App\Services\TransitionContext;

final class DraftState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::DRAFT->value;
    }

    public function label(): string
    {
        return 'Draft';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'submit_for_quote', 'label' => 'Request hosting fee quote', 'description' => 'Locks the configuration and sends the election to an administrator for pricing.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresHostingFee' => false, 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Permanently cancels this draft.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresHostingFee' => false, 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action === 'submit_for_quote') {
            $election = $lifecycle->election();
            // Never trust a fee left on a legacy draft: only an administrator
            // may establish the hosting quote in the next state.
            $election->hosting_fee = null;
            $election->currency = 'MYR';
            $lifecycle->moveTo(ElectionStatus::PENDING_FEE_QUOTE->value, $action);

            return;
        }
        $this->reject($action);
    }
}
