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
            $nationality = strtoupper((string) $attributes['nationality_country_code']);
            $isMalaysian = $nationality === 'MY';
            $icNumber = $isMalaysian ? Identity::normaliseIcNumber((string) ($attributes['ic_number'] ?? '')) : null;
            $passportNumber = $isMalaysian ? null : Identity::normalisePassportNumber((string) ($attributes['passport_number'] ?? ''));
            $dateOfBirth = $isMalaysian
                ? Identity::dateOfBirthFromMalaysianIc((string) $icNumber)
                : ($attributes['date_of_birth'] ?? null);

            if (($isMalaysian && $dateOfBirth === null) || (! $isMalaysian && (! Identity::isValidPassportNumber((string) $passportNumber) || $dateOfBirth === null))) {
                throw new \LogicException('Valid identity details are required to create an account.');
            }

            $user = new User;
            $user->forceFill([
                'name' => $fullName,
                'full_name' => $fullName,
                'ic_number' => $icNumber,
                'passport_number' => $passportNumber,
                'date_of_birth' => $dateOfBirth instanceof \DateTimeInterface ? $dateOfBirth->format('Y-m-d') : (string) $dateOfBirth,
                'nationality_country_code' => $nationality,
                'phone_number' => (string) $attributes['phone_number'],
                'email' => mb_strtolower(trim((string) $attributes['email'])),
                'password' => Hash::make((string) $attributes['password']),
                'is_admin' => false,
                'account_status' => 'ACTIVE',
                'is_verified' => false,
                'verified_at' => null,
                'verification_reference' => null,
                'verification_provider' => null,
                'verification_session_id' => null,
                'verification_status' => 'NOT_STARTED',
                'verification_requested_at' => null,
                'verification_consent_at' => null,
                'verification_last_event_id' => null,
                'verification_failure_reason' => null,
                'failed_login_attempts' => 0,
                'account_disabled_at' => null,
            ]);
            $user->save();

            return $user->refresh();
        });
    }

    public function authenticate(string $email, string $password, bool $remember = false): bool
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();
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
        $identityNameChanged = $user->full_name !== $fullName;
        $updates = [
            'name' => $fullName,
            'full_name' => $fullName,
            'email' => mb_strtolower(trim((string) $attributes['email'])),
        ];

        // The verified name is part of the eKYC identity payload. A later name change requires a fresh check.
        if ($identityNameChanged && ! $user->isAdmin()) {
            $updates += [
                'is_verified' => false,
                'verified_at' => null,
                'verification_reference' => null,
                'verification_provider' => null,
                'verification_session_id' => null,
                'verification_status' => 'NOT_STARTED',
                'verification_requested_at' => null,
                'verification_consent_at' => null,
                'verification_last_event_id' => null,
                'verification_failure_reason' => null,
            ];
        }

        $user->forceFill($updates)->save();

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
