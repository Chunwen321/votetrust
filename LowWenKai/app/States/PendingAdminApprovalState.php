<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use VoteTrust\Election\Exceptions\AuthorizationException;
use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\TransitionContext;

final class PendingAdminApprovalState extends AbstractElectionState
{
    public function name(): string
    {
        return Election::PENDING_ADMIN_APPROVAL;
    }

    public function label(): string
    {
        return 'Waiting for admin approval';
    }

    public function actions(Election $election): array
    {
        return [
            [
                'action' => 'approve',
                'label' => 'Approve election',
                'description' => 'Confirms the payment and election configuration before nominations can begin.',
                'tone' => 'primary',
                'audience' => 'admin',
                'requiresPaymentReference' => false,
                'requiresReason' => false,
            ],
            [
                'action' => 'reject',
                'label' => 'Reject election',
                'description' => 'Rejects the election permanently and records the administrator reason.',
                'tone' => 'danger',
                'audience' => 'admin',
                'requiresPaymentReference' => false,
                'requiresReason' => true,
            ],
            [
                'action' => 'cancel',
                'label' => 'Withdraw election',
                'description' => 'Allows the owning organiser to withdraw the request while approval is pending.',
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
        if (!in_array($action, ['approve', 'reject'], true)) {
            $this->reject($action);
        }
        if (!$context->actor->isAdmin()) {
            throw new AuthorizationException('Only a system administrator can approve or reject an election.');
        }

        $election = $lifecycle->election();
        if ($election->ownedBy($context->actor)) {
            throw new AuthorizationException('An administrator cannot approve their own election.');
        }
        if (!$election->paymentVerified()) {
            throw new BusinessRuleException('The election cannot be approved without a confirmed hosting payment.');
        }

        if ($action === 'approve') {
            $election->set('approved_by', $context->actor->id());
            $election->set('approved_at', $context->now->format('Y-m-d H:i:s'));
            $election->set('rejected_by', null);
            $election->set('rejected_at', null);
            $election->set('rejection_reason', null);
            $lifecycle->moveTo(Election::NOMINATION_OPEN, $action, [
                'approved_by' => $context->actor->id(),
            ]);

            return;
        }

        $reason = trim((string) $context->input('reason', ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 250) {
            throw new BusinessRuleException('A rejection reason between 5 and 250 characters is required.');
        }
        $election->set('rejected_by', $context->actor->id());
        $election->set('rejected_at', $context->now->format('Y-m-d H:i:s'));
        $election->set('rejection_reason', $reason);
        $lifecycle->moveTo(Election::REJECTED, $action, [
            'rejected_by' => $context->actor->id(),
            'reason' => $reason,
        ]);
    }
}
