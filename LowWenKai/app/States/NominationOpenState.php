<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use DateTimeImmutable;
use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\TransitionContext;

final class NominationOpenState extends AbstractElectionState
{
    public function name(): string
    {
        return Election::NOMINATION_OPEN;
    }

    public function label(): string
    {
        return 'Nominations';
    }

    public function actions(Election $election): array
    {
        return [
            [
                'action' => 'start_voting',
                'label' => 'Open voting',
                'description' => 'Allowed only after nominations end and the configured voting start time is reached.',
                'tone' => 'primary',
                'audience' => 'organizer',
                'requiresPaymentReference' => false,
                'requiresReason' => false,
            ],
            [
                'action' => 'cancel',
                'label' => 'Cancel election',
                'description' => 'Cancels the published election and records the reason.',
                'tone' => 'danger',
                'audience' => 'organizer',
                'requiresPaymentReference' => false,
                'requiresReason' => true,
            ],
        ];
    }

    public function canRegisterCandidate(Election $election, DateTimeImmutable $now): bool
    {
        return $election->paymentVerified()
            && $election->adminApproved()
            && $now >= $election->date('nomination_start')
            && $now <= $election->date('nomination_end');
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        if ($this->cancel($lifecycle, $action, $context)) {
            return;
        }
        if ($action !== 'start_voting') {
            $this->reject($action);
        }

        $election = $lifecycle->election();
        if (!$election->paymentVerified()) {
            throw new BusinessRuleException('Voting cannot open because no confirmed hosting payment is attached.');
        }
        if (!$election->adminApproved()) {
            throw new BusinessRuleException('Voting cannot open before administrator approval.');
        }
        if ($context->now < $election->date('nomination_end') || $context->now < $election->date('voting_start')) {
            throw new BusinessRuleException('Voting cannot open before nominations end and the configured voting start time is reached.');
        }

        $lifecycle->moveTo(Election::VOTING_OPEN, $action);
    }
}
