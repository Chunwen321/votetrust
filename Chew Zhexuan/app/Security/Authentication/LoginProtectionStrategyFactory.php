<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Security\Authentication;

use VoteTrust\UserAdmin\Core\Config;

final class LoginProtectionStrategyFactory
{
    public static function make(): LoginProtectionStrategy
    {
        $strategy = strtolower(trim((string) Config::get('AUTH_PROTECTION_STRATEGY', 'disable_after_failures')));

        return match ($strategy) {
            'disable_after_failures' => new DisableAccountAfterFailedAttemptsStrategy(),
            default => throw new \RuntimeException("Unsupported authentication protection strategy: {$strategy}"),
        };
    }
}
