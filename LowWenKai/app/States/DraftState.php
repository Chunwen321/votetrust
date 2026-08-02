<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\TransitionContext;

final class DraftState extends AbstractElectionState
{
    public function name(): string
    {
        return Election::DRAFT;
    }

    public function label(): string
    {
        return 'Draft';
    }

    public function actions(Election $election): array
    {
        return [
            [
                'action' => 'submit_for_payment',
                'label' => 'Submit for hosting payment',
                'description' => 'Locks the configuration and moves the election to payment verification.',
                'tone' => 'primary',
                'audience' => 'organizer',
                'requiresPaymentReference' => false,
                'requiresReason' => false,
            ],
            [
                'action' => 'cancel',
                'label' => 'Cancel election',
                'description' => 'Permanently cancels this draft.',
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

        if ($action === 'submit_for_payment') {
            $lifecycle->moveTo(Election::PENDING_PAYMENT, $action, [
                'amount_due' => (float) $lifecycle->election()->get('hosting_fee'),
                'currency' => (string) $lifecycle->election()->get('currency'),
            ]);

            return;
        }

        $this->reject($action);
    }
}
