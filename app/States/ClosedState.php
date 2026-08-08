<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Models\Election;
use App\Services\TransitionContext;

final class ClosedState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::CLOSED->value;
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
