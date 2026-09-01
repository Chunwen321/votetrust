<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Security\Authentication;

final readonly class LoginAttemptResult
{
    public const SUCCESS = 'SUCCESS';

    public const INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';

    public const ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';

    private function __construct(
        public string $status,
        public ?int $remainingAttempts = null,
    ) {}

    public static function success(): self
    {
        return new self(self::SUCCESS);
    }

    public static function invalidCredentials(?int $remainingAttempts = null): self
    {
        return new self(self::INVALID_CREDENTIALS, $remainingAttempts);
    }

    public static function accountLocked(): self
    {
        return new self(self::ACCOUNT_LOCKED, 0);
    }

    public function succeeded(): bool
    {
        return $this->status === self::SUCCESS;
    }
}
