<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;
use Illuminate\Auth\Access\AuthorizationException;

final class PendingAdminApprovalState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::PENDING_ADMIN_APPROVAL->value;
    }

    public function label(): string
    {
        return 'Awaiting admin payment verification';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'approve', 'label' => 'Verify payment & approve', 'description' => 'Checks the submitted payment with VoteTrust, then opens nominations if it is confirmed.', 'tone' => 'primary', 'audience' => 'admin', 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'return_for_payment', 'label' => 'Request payment resubmission', 'description' => 'Returns the election to the organizer so a corrected payment reference can be submitted.', 'tone' => 'secondary', 'audience' => 'admin', 'requiresPaymentReference' => false, 'requiresReason' => true],
            ['action' => 'reject', 'label' => 'Reject election', 'description' => 'Rejects the election permanently.', 'tone' => 'danger', 'audience' => 'admin', 'requiresPaymentReference' => false, 'requiresReason' => true],
            ['action' => 'cancel', 'label' => 'Withdraw election', 'description' => 'Withdraws the request while approval is pending.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if (! in_array($action, ['approve', 'return_for_payment', 'reject'], true)) {
            $this->reject($action);
        }
        if (! $context->actorIsAdmin()) {
            throw new AuthorizationException('Only a system administrator can verify, approve, return, or reject an election.');
        }

        $election = $lifecycle->election();
        if ($context->actorOwns($election)) {
            throw new AuthorizationException('An administrator cannot approve their own election.');
        }
        if ($action === 'return_for_payment') {
            $reason = trim((string) $context->input('reason', ''));
            if (mb_strlen($reason) < 5 || mb_strlen($reason) > 250) {
                throw new BusinessRuleException('A payment resubmission reason between 5 and 250 characters is required.');
            }
            $election->payment_id = null;
            $election->payment_reference = null;
            $election->payment_verified_at = null;
            $election->payment_request_id = null;
            $election->payment_provider_status = null;
            $election->payment_response_json = null;
            $lifecycle->moveTo(ElectionStatus::PENDING_PAYMENT->value, $action, ['reason' => $reason]);

            return;
        }

        if ($action === 'approve') {
            if (! $this->paymentVerified($election)) {
                $reference = trim((string) $election->payment_reference);
                if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $reference)) {
                    throw new BusinessRuleException('The organizer must submit a valid payment reference before approval.');
                }

                $result = $context->paymentGateway->verifyHostingPayment($election, $reference, $context->requestId, $context->now);
                if (! $result->paid) {
                    $context->auditLogger->log('payment_lifecycle_gate_blocked', [
                        'election_id' => $election->getKey(),
                        'request_id' => $context->requestId,
                        'provider_status' => $result->providerStatus,
                    ]);
                    throw new BusinessRuleException('Payment is not confirmed or does not match the election hosting fee.');
                }

                // The Payment module owns its records. Retain only its verified
                // API response snapshot; never mirror the provider database row.
                $election->payment_id = null;
                $election->payment_reference = $result->paymentReference;
                $election->payment_verified_at = $context->now;
                $election->payment_request_id = $context->requestId;
                $election->payment_provider_status = $result->providerStatus;
                $election->payment_response_json = $result->rawResponse;
            }

            $election->approved_by = $context->actor->getKey();
            $election->approved_at = $context->now;
            $election->rejected_by = null;
            $election->rejected_at = null;
            $election->rejection_reason = null;
            $lifecycle->moveTo(ElectionStatus::NOMINATION_OPEN->value, $action, [
                'approved_by' => $context->actor->getKey(),
                'payment_reference' => $election->payment_reference,
                'provider_status' => $election->payment_provider_status,
            ]);

            return;
        }

        $reason = trim((string) $context->input('reason', ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 250) {
            throw new BusinessRuleException('A rejection reason between 5 and 250 characters is required.');
        }
        $election->rejected_by = $context->actor->getKey();
        $election->rejected_at = $context->now;
        $election->rejection_reason = $reason;
        $lifecycle->moveTo(ElectionStatus::REJECTED->value, $action, ['rejected_by' => $context->actor->getKey(), 'reason' => $reason]);
    }
}
