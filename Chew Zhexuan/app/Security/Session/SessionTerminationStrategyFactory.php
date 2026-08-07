<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Security\Session;

use VoteTrust\UserAdmin\Core\Config;

final class SessionTerminationStrategyFactory
{
    public static function make(): SessionTerminationStrategy
    {
        $strategy = strtolower(trim((string) Config::get('LOGOUT_STRATEGY', 'full_session_termination')));

        return match ($strategy) {
            'full_session_termination' => new FullSessionTerminationStrategy(),
            default => throw new \RuntimeException("Unsupported logout strategy: {$strategy}"),
        };
    }
}
