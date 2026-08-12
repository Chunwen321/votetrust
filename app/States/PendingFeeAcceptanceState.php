<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;

final class PendingFeeAcceptanceState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::PENDING_FEE_ACCEPTANCE->value;
    }

    public function label(): string
    {
        return 'Awaiting organizer fee acceptance';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'accept_fee', 'label' => 'Accept quote', 'description' => 'Accepts the administrator\'s fee and enables hosting payment.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresHostingFee' => false, 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'request_fee_review', 'label' => 'Request revised quote', 'description' => 'Returns the request to the administrator with your reason.', 'tone' => 'secondary', 'audience' => 'organizer', 'requiresHostingFee' => false, 'requiresPaymentReference' => false, 'requiresReason' => true],
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Declines the quote and cancels the election.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresHostingFee' => false, 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }

        $election = $lifecycle->election();
        if ($action === 'accept_fee') {
            if ((float) $election->hosting_fee < 1 || ! preg_match('/^[A-Z]{3}$/', (string) $election->currency)) {
                throw new BusinessRuleException('The administrator must provide a valid hosting fee before it can be accepted.');
            }
            $lifecycle->moveTo(ElectionStatus::PENDING_PAYMENT->value, $action, [
                'amount_due' => (float) $election->hosting_fee,
                'currency' => (string) $election->currency,
            ]);

            return;
        }

        if ($action === 'request_fee_review') {
            $reason = trim((string) $context->input('reason', ''));
            if (mb_strlen($reason) < 5 || mb_strlen($reason) > 250) {
                throw new BusinessRuleException('A fee review reason between 5 and 250 characters is required.');
            }
            $election->hosting_fee = null;
            $election->currency = 'MYR';
            $lifecycle->moveTo(ElectionStatus::PENDING_FEE_QUOTE->value, $action, ['reason' => $reason]);

            return;
        }

        $this->reject($action);
    }
}
