<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;
use App\ValueObjects\EligibilityRules;

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
            ['action' => 'submit_for_payment', 'label' => 'Continue to hosting payment', 'description' => 'Locks the configuration and continues with the fixed hosting fee for this election type.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Permanently cancels this draft.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }

        if ($action === 'submit_for_payment') {
            $election = $lifecycle->election();
            if (EligibilityRules::fromStored($election->eligibility_rules)->mode() === EligibilityRules::PRIVATE
                && $election->private_voter_list_uploaded_at === null) {
                throw new BusinessRuleException('Upload a private voter spreadsheet or assign at least one voter before submitting this election for payment.');
            }

            // Re-derive the fee at the payment boundary so a stale browser
            // value or an older draft can never influence the amount due.
            $election->applyDefaultHostingFee();
            $lifecycle->moveTo(ElectionStatus::PENDING_PAYMENT->value, $action, [
                'amount_due' => (float) $election->hosting_fee,
                'currency' => (string) $election->currency,
            ]);

            return;
        }
        $this->reject($action);
    }
}
