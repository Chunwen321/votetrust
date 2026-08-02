<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use DateTimeImmutable;
use VoteTrust\Election\Exceptions\AuthorizationException;
use VoteTrust\Election\Exceptions\BusinessRuleException;
use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\TransitionContext;

abstract class AbstractElectionState implements ElectionState
{
    public function canRegisterCandidate(Election $election, DateTimeImmutable $now): bool
    {
        return false;
    }

    public function canVote(Election $election, DateTimeImmutable $now): bool
    {
        return false;
    }

    protected function cancel(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): bool
    {
        if ($action !== 'cancel') {
            return false;
        }

        if (!$context->actor->isOrganizer() || !$lifecycle->election()->ownedBy($context->actor)) {
            throw new AuthorizationException('Only the owning organiser can cancel this election.');
        }

        $lifecycle->moveTo(Election::CANCELLED, $action, [
            'reason' => trim((string) $context->input('reason', 'Cancelled by organiser')),
        ]);

        return true;
    }

    protected function reject(string $action): never
    {
        throw new BusinessRuleException(sprintf(
            'Action "%s" is not allowed while the election is %s.',
            $action,
            strtolower(str_replace('_', ' ', $this->name()))
        ));
    }
}
