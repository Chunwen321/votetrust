<?php

declare(strict_types=1);

namespace VoteTrust\Election\States;

use VoteTrust\Election\Models\Election;
use VoteTrust\Election\Services\TransitionContext;

final class ElectionLifecycle
{
    private ElectionState $state;

    /** @var array{action:string,from:string,to:string,metadata:array<string,mixed>}|null */
    private ?array $lastTransition = null;

    public function __construct(
        private readonly Election $election,
        private readonly ElectionStateFactory $factory = new ElectionStateFactory()
    ) {
        $this->state = $this->factory->make($election->status());
    }

    public function election(): Election
    {
        return $this->election;
    }

    public function state(): ElectionState
    {
        return $this->state;
    }

    public function transition(string $action, TransitionContext $context): void
    {
        $this->state->apply($this, $action, $context);
    }

    /** @param array<string, mixed> $metadata */
    public function moveTo(string $stateName, string $action, array $metadata = []): void
    {
        $from = $this->state->name();
        $next = $this->factory->make($stateName);
        $this->election->set('status', $next->name());
        $this->state = $next;
        $this->lastTransition = [
            'action' => $action,
            'from' => $from,
            'to' => $next->name(),
            'metadata' => $metadata,
        ];
    }

    /** @return array{action:string,from:string,to:string,metadata:array<string,mixed>}|null */
    public function lastTransition(): ?array
    {
        return $this->lastTransition;
    }
}

