<?php

declare(strict_types=1);

namespace VoteTrust\Election\Security;

use VoteTrust\Election\Exceptions\AuthorizationException;
use VoteTrust\Election\Models\User;

final class ReauthenticationGuard
{
    public function __construct(private readonly SecurityAuditLogger $logger = new SecurityAuditLogger())
    {
    }

    public function assertPassword(User $user, string $password, string $operation): void
    {
        if ($password === '' || !password_verify($password, $user->passwordHash())) {
            $this->logger->log('critical_operation_reauthentication_failed', [
                'user_id' => $user->id(),
                'operation' => $operation,
            ]);
            throw new AuthorizationException('Your current password is required before this critical operation can continue.');
        }

        $this->logger->log('critical_operation_reauthenticated', [
            'user_id' => $user->id(),
            'operation' => $operation,
        ]);
    }
}

