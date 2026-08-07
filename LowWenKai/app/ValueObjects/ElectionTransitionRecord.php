<?php

declare(strict_types=1);

namespace VoteTrust\Election\ValueObjects;

use VoteTrust\Election\Models\User;

/**
 * Read-only representation of one entry in election.transition_history_json.
 * This class is not mapped to a database table.
 */
final class ElectionTransitionRecord
{
    /** @param array<string, mixed> $attributes */
    public function __construct(private readonly array $attributes)
    {
    }

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
        return User::find((int) $this->get('actor_user_id'));
    }
}
