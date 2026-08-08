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
            ['action' => 'submit_for_payment', 'label' => 'Submit for hosting payment', 'description' => 'Locks the configuration and moves the election to payment verification.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Permanently cancels this draft.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action === 'submit_for_payment') {
            $lifecycle->moveTo(ElectionStatus::PENDING_PAYMENT->value, $action, [
                'amount_due' => (float) $lifecycle->election()->hosting_fee,
                'currency' => (string) $lifecycle->election()->currency,
            ]);

            return;
        }
        $this->reject($action);
    }
}
