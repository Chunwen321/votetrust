<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Security\Authentication;

use App\Models\User;
use Illuminate\Container\Attributes\Bind;

#[Bind(DisableAccountAfterFailedAttemptsStrategy::class)]
interface LoginProtectionStrategy
{
    public function allows(User $user): bool;

    public function recordFailure(User $user): void;

    public function recordSuccess(User $user): void;
}
