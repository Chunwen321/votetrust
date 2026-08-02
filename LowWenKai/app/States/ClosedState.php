<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\TransitionContext;

final class ClosedState extends AbstractElectionState
{
    public function name(): string
    {
        return Election::CLOSED;
    }

    public function label(): string
    {
        return 'Closed';
    }

    public function actions(Election $election): array
    {
        return [];
    }

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void
    {
        $this->reject($action);
    }
}

