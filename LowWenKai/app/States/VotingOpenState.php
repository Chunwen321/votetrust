<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use DateTimeImmutable;
use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\TransitionContext;

final class VotingOpenState extends AbstractElectionState
{
    public function name(): string
    {
        return Election::VOTING_OPEN;
    }

    public function label(): string
    {
        return 'Voting open';
    }

    public function actions(Election $election): array
    {
        return [
            [
                'action' => 'close_voting',
                'label' => 'Close election',
                'description' => 'Allowed only after the configured voting end time.',
                'tone' => 'primary',
                'audience' => 'organizer',
                'requiresPaymentReference' => false,
                'requiresReason' => false,
            ],
            [
                'action' => 'cancel',
                'label' => 'Emergency cancellation',
                'description' => 'Cancels an active election and records the reason.',
                'tone' => 'danger',
                'audience' => 'organizer',
                'requiresPaymentReference' => false,
                'requiresReason' => true,
            ],
        ];
    }

    public function canVote(Election $election, DateTimeImmutable $now): bool
    {
        return $election->paymentVerified()
            && $election->adminApproved()
            && $now >= $election->date('voting_start')
            && $now <= $election->date('voting_end');
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action !== 'close_voting') {
            $this->reject($action);
        }

        $election = $lifecycle->election();
        if ($context->now < $election->date('voting_end')) {
            throw new BusinessRuleException('The election cannot close before the configured voting end time.');
        }

        $lifecycle->moveTo(Election::CLOSED, $action);
    }
}
