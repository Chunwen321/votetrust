<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Election;
use App\Models\Organizer;
use App\Models\User;
use App\Security\SecurityAuditLogger;
use Carbon\CarbonImmutable;

final readonly class TransitionContext
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public User $actor,
        public CarbonImmutable $now,
        public string $requestId,
        public PaymentGateway $paymentGateway,
        public SecurityAuditLogger $auditLogger,
        private array $input = [],
    ) {}

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function actorIsAdmin(): bool
    {
        if (method_exists($this->actor, 'isAdmin')) {
            return (bool) $this->actor->isAdmin();
        }

        return (bool) ($this->actor->is_admin ?? false)
            || strtolower((string) ($this->actor->role ?? '')) === 'admin';
    }

    public function actorOwns(Election $election): bool
    {
        $organizer = Organizer::query()->find($election->organizer_id);

        return (int) ($organizer?->user_id ?? $election->organizer_id) === (int) $this->actor->getKey();
    }
}
