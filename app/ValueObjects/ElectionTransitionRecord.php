<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\ValueObjects;

use App\Models\User;

final readonly class ElectionTransitionRecord
{
    public const LIFECYCLE_TRANSITION = 'LIFECYCLE_TRANSITION';

    public const DOMAIN_EVENT = 'DOMAIN_EVENT';

    /** @param array<string, mixed> $attributes */
    public function __construct(private array $attributes) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function actor(): ?User
    {
        return User::query()->find((int) $this->get('actor_user_id'));
    }

    public function actorLabel(): string
    {
        $actor = $this->actor();
        if (! $actor instanceof User) {
            return 'User #'.$this->get('actor_user_id');
        }

        return $actor->isAdmin() ? 'SystemAdmin' : $actor->display_name;
    }

    public function type(): string
    {
        $type = strtoupper(trim((string) $this->get('type')));
        if (in_array($type, [self::LIFECYCLE_TRANSITION, self::DOMAIN_EVENT], true)) {
            return $type;
        }

        return $this->get('from_state') === $this->get('to_state')
            ? self::DOMAIN_EVENT
            : self::LIFECYCLE_TRANSITION;
    }

    public function changesLifecycleState(): bool
    {
        return $this->type() === self::LIFECYCLE_TRANSITION;
    }
}
