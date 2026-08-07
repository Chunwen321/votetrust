<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\TransitionContext;

final class PendingPaymentState extends AbstractElectionState
{
    public function name(): string
    {
        return Election::PENDING_PAYMENT;
    }

    public function label(): string
    {
        return 'Pending payment';
    }

    public function actions(Election $election): array
    {
        return [
            [
                'action' => 'verify_payment',
                'label' => 'Pay hosting fee',
                'description' => 'Completes the hosting payment through the Payment Module. After confirmation, the election is sent to the administrator for approval.',
                'tone' => 'primary',
                'audience' => 'organizer',
                'requiresPaymentReference' => true,
                'requiresReason' => false,
            ],
            [
                'action' => 'cancel',
                'label' => 'Cancel election',
                'description' => 'Cancels the unpaid election.',
                'tone' => 'danger',
                'audience' => 'organizer',
                'requiresPaymentReference' => false,
                'requiresReason' => true,
            ],
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

        $paymentReference = trim((string) $context->input('payment_reference', ''));
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $paymentReference)) {
            throw new BusinessRuleException('Enter a valid payment reference before verification.');
        }

        $election = $lifecycle->election();
        $result = $context->paymentGateway->verifyHostingPayment(
            $election,
            $paymentReference,
            $context->requestId,
            $context->now
        );

        if (!$result->paid) {
            $context->auditLogger->log('payment_lifecycle_gate_blocked', [
                'election_id' => $election->id(),
                'request_id' => $context->requestId,
                'provider_status' => $result->providerStatus,
            ]);
            throw new BusinessRuleException('Payment is not confirmed or does not match the election hosting fee. The lifecycle remains unchanged.');
        }

        $election->set('payment_id', $result->paymentId);
        $election->set('payment_reference', $result->paymentReference);
        $election->set('payment_verified_at', $context->now->format('Y-m-d H:i:s'));
        $election->set('payment_request_id', $context->requestId);
        $election->set('payment_provider_status', $result->providerStatus);
        $election->set('payment_response_json', json_encode(
            $result->rawResponse,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));

        $lifecycle->moveTo(Election::PENDING_ADMIN_APPROVAL, $action, [
            'payment_reference' => $result->paymentReference,
            'provider_status' => $result->providerStatus,
            'verified_amount' => $result->amount,
            'currency' => $result->currency,
            'next_gate' => Election::PENDING_ADMIN_APPROVAL,
        ]);
    }
}
