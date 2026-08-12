<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\States;

use App\Models\Election;
use App\Services\TransitionContext;
use Carbon\CarbonImmutable;

interface ElectionState
{
    public function name(): string;

    public function label(): string;

    /** @return list<array{action:string,label:string,description:string,tone:string,audience:string,requiresHostingFee:bool,requiresPaymentReference:bool,requiresReason:bool}> */
    public function actions(Election $election): array;

    public function canRegisterCandidate(Election $election, CarbonImmutable $now): bool;

    public function canVote(Election $election, CarbonImmutable $now): bool;

    public function apply(ElectionLifecycle $lifecycle, string $action, TransitionContext $context): void;
}
