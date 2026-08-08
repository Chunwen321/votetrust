<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\ValueObjects;

use App\Models\User;

final readonly class ElectionTransitionRecord
{
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
}
