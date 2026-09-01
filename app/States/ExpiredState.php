<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Models\Election;
use App\Services\TransitionContext;

/** A terminal public-election state that requires the organiser to start again. */
final class ExpiredState extends AbstractElectionState
{
    public function name(): string
    {
        return ElectionStatus::EXPIRED->value;
    }

    public function label(): string
    {
        return 'Schedule expired';
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
