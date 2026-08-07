<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Services;

use VoteTrust\UserAdmin\Exceptions\ValidationException;
use VoteTrust\UserAdmin\Models\UserRepository;
use VoteTrust\UserAdmin\Support\Identity;

final class UserService
{
    public function __construct(private readonly UserRepository $users = new UserRepository())
    {
    }

    /** @return array<string, mixed> */
    public function register(
        string $username,
        string $fullName,
        string $email,
        string $icNumber,
        string $password,
        string $passwordConfirmation
    ): array
    {
        $username = trim($username);
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName) ?? '');
        $email = strtolower(trim($email));
        $icNumber = Identity::normaliseIcNumber($icNumber);
        $this->validateRegistration($username, $fullName, $email, $icNumber, $password, $passwordConfirmation);

        return $this->users->createUser(
            $username,
            $fullName,
            $email,
            $icNumber,
            password_hash($password, PASSWORD_DEFAULT)
        );
    }

    /** @param array<string, mixed> $user */
    public function updateProfile(array $user, string $fullName, string $email): void
    {
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName) ?? '');
        $email = strtolower(trim($email));
        $errors = [];
        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            $errors['full_name'] = 'Enter a name between 2 and 100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ($this->users->emailExists($email, (int) $user['id'])) {
            $errors['email'] = 'That email address is already registered.';
        }
        if ($errors !== []) {
            throw new ValidationException(errors: $errors);
        }

        $this->users->updateProfile((int) $user['id'], $fullName, $email);
    }

    /** @param array<string, mixed> $user */
    public function changePassword(array $user, string $currentPassword, string $newPassword, string $confirmation): void
    {
        $errors = [];
        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
        $this->validatePassword($newPassword, $confirmation, $errors);
        if ($errors !== []) {
            throw new ValidationException(errors: $errors);
        }

        $this->users->updatePassword((int) $user['id'], password_hash($newPassword, PASSWORD_DEFAULT));
    }

    private function validateRegistration(
        string $username,
        string $fullName,
        string $email,
        string $icNumber,
        string $password,
        string $passwordConfirmation
    ): void
    {
        $errors = [];
        if (preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) !== 1) {
            $errors['username'] = 'Use 3–50 letters, numbers, dots, underscores, or hyphens.';
        } elseif ($this->users->usernameExists($username)) {
            $errors['username'] = 'That username is already registered.';
        }
        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            $errors['full_name'] = 'Enter a name between 2 and 100 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ($this->users->emailExists($email)) {
            $errors['email'] = 'That email address is already registered.';
        }
        if (!Identity::isValidIcNumber($icNumber)) {
            $errors['ic_number'] = 'Enter a valid 12-digit IC number.';
        } elseif ($this->users->icNumberExists($icNumber)) {
            $errors['ic_number'] = 'That IC number is already registered.';
        }
        $this->validatePassword($password, $passwordConfirmation, $errors);
        if ($errors !== []) {
            throw new ValidationException(errors: $errors);
        }
    }

    /** @param array<string, string> $errors */
    private function validatePassword(string $password, string $confirmation, array &$errors): void
    {
        if (strlen($password) < 12
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/\d/', $password) !== 1
            || preg_match('/[^A-Za-z0-9]/', $password) !== 1) {
            $errors['password'] = 'Use at least 12 characters with upper- and lower-case letters, a number, and a symbol.';
        }
        if ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Password confirmation does not match.';
        }
    }
}
