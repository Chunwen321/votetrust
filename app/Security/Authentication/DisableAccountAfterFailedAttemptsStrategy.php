<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Security\Authentication;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DisableAccountAfterFailedAttemptsStrategy implements LoginProtectionStrategy
{
    public function allows(User $user): bool
    {
        return ($user->account_status ?? 'ACTIVE') === 'ACTIVE';
    }

    public function recordFailure(User $user): void
    {
        DB::transaction(function () use ($user): void {
            /** @var User|null $lockedUser */
            $lockedUser = User::query()->lockForUpdate()->find($user->getKey());
            if (! $lockedUser instanceof User || ! $this->allows($lockedUser)) {
                return;
            }

            $maximum = max(1, (int) config('votetrust-user.auth.max_failed_attempts', 5));
            $attempts = min(65535, ((int) $lockedUser->failed_login_attempts) + 1);
            $attributes = ['failed_login_attempts' => $attempts];

            if ($attempts >= $maximum) {
                $attributes['account_status'] = 'DISABLED';
                $attributes['account_disabled_at'] = $lockedUser->account_disabled_at ?? now();
            }

            $lockedUser->forceFill($attributes)->save();
        });
    }

    public function recordSuccess(User $user): void
    {
        $user->forceFill([
            'failed_login_attempts' => 0,
            'account_disabled_at' => null,
        ])->save();
    }
}
