<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Exceptions\BusinessRuleException;
use App\Models\Election;
use App\Services\TransitionContext;
use Illuminate\Auth\Access\AuthorizationException;

final class PendingFeeQuoteState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::PENDING_FEE_QUOTE->value;
    }

    public function label(): string
    {
        return 'Awaiting admin fee quote';
    }

    public function actions(Election $election): array
    {
        return [
            ['action' => 'quote_fee', 'label' => 'Send hosting fee quote', 'description' => 'Set the hosting price for the organizer to review.', 'tone' => 'primary', 'audience' => 'admin', 'requiresHostingFee' => true, 'requiresPaymentReference' => false, 'requiresReason' => false],
            ['action' => 'reject', 'label' => 'Reject election', 'description' => 'Rejects this election request without issuing a quote.', 'tone' => 'danger', 'audience' => 'admin', 'requiresHostingFee' => false, 'requiresPaymentReference' => false, 'requiresReason' => true],
            ['action' => 'cancel', 'label' => 'Withdraw request', 'description' => 'Withdraws the election while it is awaiting a quote.', 'tone' => 'danger', 'audience' => 'organizer', 'requiresHostingFee' => false, 'requiresPaymentReference' => false, 'requiresReason' => true],
        ];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if (! in_array($action, ['quote_fee', 'reject'], true)) {
            $this->reject($action);
        }
        if (! $context->actorIsAdmin()) {
            throw new AuthorizationException('Only a system administrator can quote or reject an election.');
        }

        $election = $lifecycle->election();
        if ($context->actorOwns($election)) {
            throw new AuthorizationException('An administrator cannot review their own election.');
        }

        if ($action === 'quote_fee') {
            $amount = filter_var($context->input('hosting_fee'), FILTER_VALIDATE_FLOAT);
            $currency = strtoupper(trim((string) $context->input('currency', 'MYR')));
            if ($amount === false || $amount < 1 || $amount > 10000) {
                throw new BusinessRuleException('Enter a hosting fee between 1.00 and 10,000.00.');
            }
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new BusinessRuleException('Enter a valid three-letter currency code.');
            }

            $election->hosting_fee = number_format((float) $amount, 2, '.', '');
            $election->currency = $currency;
            $lifecycle->moveTo(ElectionStatus::PENDING_FEE_ACCEPTANCE->value, $action, [
                'amount_due' => (float) $amount,
                'currency' => $currency,
                'quoted_by' => $context->actor->getKey(),
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
        $lifecycle->moveTo(ElectionStatus::REJECTED->value, $action, [
            'rejected_by' => $context->actor->getKey(),
            'reason' => $reason,
        ]);
    }
}
