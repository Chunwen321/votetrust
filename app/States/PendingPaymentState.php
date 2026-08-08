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
            ['action' => 'verify_payment', 'label' => 'Pay hosting fee', 'description' => 'Verifies the hosting payment before administrator review.', 'tone' => 'primary', 'audience' => 'organizer', 'requiresPaymentReference' => true, 'requiresReason' => false],
            ['action' => 'cancel', 'label' => 'Cancel election', 'description' => 'Cancels the unpaid election.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action !== 'verify_payment') {
            $this->reject($action);
        }

        $reference = trim((string) $context->input('payment_reference', ''));
        if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $reference)) {
            throw new BusinessRuleException('Enter a valid payment reference before verification.');
        }

        $election = $lifecycle->election();
        $result = $context->paymentGateway->verifyHostingPayment($election, $reference, $context->requestId, $context->now);
        if (! $result->paid) {
            $context->auditLogger->log('payment_lifecycle_gate_blocked', [
                'election_id' => $election->getKey(),
                'request_id' => $context->requestId,
                'provider_status' => $result->providerStatus,
            ]);
            throw new BusinessRuleException('Payment is not confirmed or does not match the election hosting fee.');
        }

        // The Payment module owns its records. Retain only the verified IFA
        // response snapshot; never query or mirror the provider's database row.
        $election->payment_id = null;
        $election->payment_reference = $result->paymentReference;
        $election->payment_verified_at = $context->now;
        $election->payment_request_id = $context->requestId;
        $election->payment_provider_status = $result->providerStatus;
        $election->payment_response_json = $result->rawResponse;
        $lifecycle->moveTo(ElectionStatus::PENDING_ADMIN_APPROVAL->value, $action, [
            'payment_reference' => $result->paymentReference,
            'provider_status' => $result->providerStatus,
            'provider_payment_id' => $result->paymentId,
            'verified_amount' => $result->amount,
            'currency' => $result->currency,
        ]);
    }
}
