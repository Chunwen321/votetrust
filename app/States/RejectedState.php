<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Models\Election;
use App\Services\TransitionContext;

final class RejectedState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::REJECTED->value;
    }

    public function label(): string
    {
        return 'Rejected';
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
