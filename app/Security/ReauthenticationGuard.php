<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Security;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $this->logger->log('critical_operation_reauthenticated', [
            'user_id' => $user->getKey(),
            'operation' => $operation,
        ]);
    }
}
