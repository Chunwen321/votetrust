<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Security;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

final class ReauthenticationGuard
{
    public function __construct(private readonly SecurityAuditLogger $logger = new SecurityAuditLogger) {}

    public function assertPassword(User $user, string $password, string $operation): void
    {
        $hash = (string) $user->getAuthPassword();
        if ($password === '' || $hash === '' || ! Hash::check($password, $hash)) {
            $this->logger->log('critical_operation_reauthentication_failed', [
                'user_id' => $user->getKey(),
                'operation' => $operation,
            ]);
            throw new AuthorizationException('Your current password is required before this critical operation can continue.');
        }

        $this->logger->log('critical_operation_reauthenticated', [
            'user_id' => $user->getKey(),
            'operation' => $operation,
        ]);
    }
}
