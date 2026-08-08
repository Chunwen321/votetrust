<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Security\Authentication\LoginProtectionStrategy;
use App\Support\Identity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UserService
{
    private const DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(private readonly LoginProtectionStrategy $loginProtection) {}

    /** @param array<string, mixed> $attributes */
    public function register(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $fullName = $this->normaliseName((string) $attributes['full_name']);
            $user = new User;
            $user->forceFill([
                'name' => $fullName,
                'username' => trim((string) $attributes['username']),
                'full_name' => $fullName,
                'ic_number' => Identity::normaliseIcNumber((string) $attributes['ic_number']),
                'email' => mb_strtolower(trim((string) $attributes['email'])),
                'password' => Hash::make((string) $attributes['password']),
                'is_admin' => false,
                'account_status' => 'ACTIVE',
                'failed_login_attempts' => 0,
                'account_disabled_at' => null,
            ]);
            $user->save();

            return $user->refresh();
        });
    }

    public function authenticate(string $username, string $password, bool $remember = false): bool
    {
        $user = User::query()->where('username', trim($username))->first();
        $passwordMatches = Hash::check($password, $user?->password ?? self::DUMMY_PASSWORD_HASH);

        if (! $user instanceof User) {
            return false;
        }

        if (! $this->loginProtection->allows($user)) {
            return false;
        }

        if (! $passwordMatches) {
            $this->loginProtection->recordFailure($user);

            return false;
        }

        $this->loginProtection->recordSuccess($user);

        Auth::login($user, $remember);

        return true;
    }

    /** @param array<string, mixed> $attributes */
    public function updateProfile(User $user, array $attributes): User
    {
        $fullName = $this->normaliseName((string) $attributes['full_name']);
        $user->forceFill([
            'name' => $fullName,
            'full_name' => $fullName,
            'email' => mb_strtolower(trim((string) $attributes['email'])),
        ])->save();

        return $user->refresh();
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->forceFill(['password' => Hash::make($password)])->save();
    }

    private function normaliseName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }
}
