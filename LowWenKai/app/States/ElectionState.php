<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\TransitionContext;

interface ElectionState
{
    public function name(): string;

    public function label(): string;

    /** @return list<array{action:string,label:string,description:string,tone:string,audience:string,requiresPaymentReference:bool,requiresReason:bool}> */
    public function actions(Election $election): array;

    public function canRegisterCandidate(Election $election, \DateTimeImmutable $now): bool;

    public function canVote(Election $election, \DateTimeImmutable $now): bool;

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void;
}
