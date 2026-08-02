<?php

declare(strict_types=1);

namespace VoteTrust\Election\Services;

use DateTimeImmutable;
use VoteTrust\Election\Models\User;
use VoteTrust\Election\Security\SecurityAuditLogger;
use VoteTrust\Election\Services\Payment\PaymentGateway;

final class TransitionContext
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly User $actor,
        public readonly DateTimeImmutable $now,
        public readonly string $requestId,
        public readonly PaymentGateway $paymentGateway,
        public readonly SecurityAuditLogger $auditLogger,
        private readonly array $input = []
    ) {
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }
}

