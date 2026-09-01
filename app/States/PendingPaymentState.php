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
            ['action' => 'submit_payment', 'label' => 'Send for administrator review', 'description' => 'Complete the hosting-fee checkout first. Once payment is confirmed, enter your password to send the election for administrator approval.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => false],
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
        if ($this->expireOverduePublicElection($lifecycle, $context, $action)) {
            return;
        }

        $election = $lifecycle->election();
        $election->applyDefaultHostingFee();

        // The disabled browser button is only a usability guard. Always ask
        // the Payment module again so a crafted request cannot bypass payment.
        $result = $context->paymentGateway->verifyHostingPayment($election, null, $context->requestId, $context->now);
        if (! $result->paid) {
            $context->auditLogger->log('payment_lifecycle_gate_blocked', [
                'election_id' => $election->getKey(),
                'request_id' => $context->requestId,
                'provider_status' => $result->providerStatus,
            ]);
            throw new BusinessRuleException('Hosting payment has not been confirmed. Complete the payment before sending this election for administrator review.');
        }

        $election->payment_id = null;
        $election->payment_reference = $result->paymentReference;
        $election->payment_verified_at = $context->now;
        $election->payment_request_id = $context->requestId;
        $election->payment_provider_status = $result->providerStatus;
        $election->payment_response_json = $result->rawResponse;
        $lifecycle->moveTo(ElectionStatus::PENDING_ADMIN_APPROVAL->value, $action, [
            'payment_id' => $result->paymentId,
            'payment_reference' => $result->paymentReference,
            'provider_status' => $result->providerStatus,
            'lookup' => 'ELECTION_ID',
            'verification_source' => 'PAYMENT_API',
            'amount_due' => (float) $election->hosting_fee,
            'currency' => (string) $election->currency,
        ]);
    }
}
