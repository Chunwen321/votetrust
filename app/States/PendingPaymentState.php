<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;

final class PendingPaymentState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::PENDING_PAYMENT->value;
    }

    public function label(): string
    {
        return 'Pending payment';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'submit_payment', 'label' => 'Submit payment for verification', 'description' => 'Pay the fixed hosting fee and send the payment reference to an administrator for verification.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresPaymentReference' => true, 'requiresReason' => false],
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Cancels the unpaid election.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action !== 'submit_payment') {
            $this->reject($action);
        }

        $reference = trim((string) $context->input('payment_reference', ''));
        if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $reference)) {
            throw new BusinessRuleException('Enter a valid payment reference before verification.');
        }

        $election = $lifecycle->election();
        $election->applyDefaultHostingFee();
        // The organizer supplies evidence of payment. Verification remains an
        // administrator-only responsibility in PendingAdminApprovalState.
        $election->payment_id = null;
        $election->payment_reference = $reference;
        $election->payment_verified_at = null;
        $election->payment_request_id = null;
        $election->payment_provider_status = null;
        $election->payment_response_json = null;
        $lifecycle->moveTo(ElectionStatus::PENDING_ADMIN_APPROVAL->value, $action, [
            'payment_reference' => $reference,
            'amount_due' => (float) $election->hosting_fee,
            'currency' => (string) $election->currency,
        ]);
    }
}
