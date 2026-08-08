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
        return 'Waiting for admin approval';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'approve', 'label' => 'Approve election', 'description' => 'Approves the configuration and opens nominations.', 'tone' => 'primary', 'audience' => 'admin', 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'reject', 'label' => 'Reject election', 'description' => 'Rejects the election permanently.', 'tone' => 'danger', 'audience' => 'admin', 'requiresPaymentReference' => false, 'requiresReason' => true],
            ['action' => 'cancel', 'label' => 'Withdraw election', 'description' => 'Withdraws the request while approval is pending.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if (! in_array($action, ['approve', 'reject'], true)) {
            $this->reject($action);
        }
        if (! $context->actorIsAdmin()) {
            throw new AuthorizationException('Only a system administrator can approve or reject an election.');
        }

        $election = $lifecycle->election();
        if ($context->actorOwns($election)) {
            throw new AuthorizationException('An administrator cannot approve their own election.');
        }
        if (! $this->paymentVerified($election)) {
            throw new BusinessRuleException('The election cannot be approved without a confirmed hosting payment.');
        }

        if ($action === 'approve') {
            $election->approved_by = $context->actor->getKey();
            $election->approved_at = $context->now;
            $election->rejected_by = null;
            $election->rejected_at = null;
            $election->rejection_reason = null;
            $lifecycle->moveTo(ElectionStatus::NOMINATION_OPEN->value, $action, ['approved_by' => $context->actor->getKey()]);

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
