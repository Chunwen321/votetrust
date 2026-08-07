<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Models;

use VoteTrust\UserAdmin\Core\Database;
use PDO;

final class UserRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->findOne('u.id = :id', [':id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->findOne('u.username = :username', [':username' => $username]);
    }

    public function usernameExists(string $username, ?int $excludingId = null): bool
    {
        return $this->attributeExists('username', $username, $excludingId);
    }

    public function emailExists(string $email, ?int $excludingId = null): bool
    {
        return $this->attributeExists('email', $email, $excludingId);
    }

    public function icNumberExists(string $icNumber, ?int $excludingId = null): bool
    {
        return $this->attributeExists('ic_number', $icNumber, $excludingId);
    }

    /** @return array<string, mixed> */
    public function createUser(
        string $username,
        string $fullName,
        string $email,
        string $icNumber,
        string $passwordHash
    ): array
    {
        return Database::transaction(function (PDO $pdo) use ($username, $fullName, $email, $icNumber, $passwordHash): array {
            $statement = $pdo->prepare(
                'INSERT INTO `user` (
                    `username`, `full_name`, `email`, `ic_number`,
                    `password_hash`, `account_status`, `created_at`, `updated_at`
                 ) VALUES (
                    :username, :full_name, :email, :ic_number,
                    :password_hash, \'ACTIVE\', NOW(), NOW()
                 )'
            );
            $statement->execute([
                ':username' => $username,
                ':full_name' => $fullName,
                ':email' => $email,
                ':ic_number' => $icNumber,
                ':password_hash' => $passwordHash,
            ]);
            $id = (int) $pdo->lastInsertId();

            $created = $this->findById($id);
            if ($created === null) {
                throw new \RuntimeException('The new account could not be loaded.');
            }

            return $created;
        });
    }

    public function updateProfile(int $id, string $fullName, string $email): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `user`
             SET `full_name` = :full_name,
                 `email` = :email,
                 `updated_at` = NOW()
             WHERE `id` = :id'
        );
        $statement->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':id' => $id,
        ]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `user` SET `password_hash` = :password_hash, `updated_at` = NOW() WHERE `id` = :id'
        );
        $statement->execute([':password_hash' => $passwordHash, ':id' => $id]);
    }

    public function incrementFailedLoginAttempts(int $id, int $maximumAttempts): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `user`
             SET `account_status` = CASE
                    WHEN `failed_login_attempts` + 1 >= :status_threshold THEN \'DISABLED\'
                    ELSE `account_status`
                 END,
                 `account_disabled_at` = CASE
                    WHEN `failed_login_attempts` + 1 >= :disabled_threshold THEN COALESCE(`account_disabled_at`, NOW())
                    ELSE `account_disabled_at`
                 END,
                 `failed_login_attempts` = LEAST(`failed_login_attempts` + 1, 65535),
                 `updated_at` = NOW()
             WHERE `id` = :id AND `account_status` = \'ACTIVE\''
        );
        $statement->execute([
            ':status_threshold' => $maximumAttempts,
            ':disabled_threshold' => $maximumAttempts,
            ':id' => $id,
        ]);
    }

    public function clearFailedLoginAttempts(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE `user`
             SET `failed_login_attempts` = 0, `account_disabled_at` = NULL, `updated_at` = NOW()
             WHERE `id` = :id AND `account_status` = \'ACTIVE\''
        );
        $statement->execute([':id' => $id]);
    }

    /** @return array<string, mixed>|null */
    private function findOne(string $where, array $parameters): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT u.*, (u.`is_admin` = 0 AND EXISTS(SELECT 1 FROM `voter` v WHERE v.`user_id` = u.`id`)) AS is_voter
             FROM `user` u
             WHERE ' . $where . '
             LIMIT 1'
        );
        $statement->execute($parameters);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    private function attributeExists(string $column, string $value, ?int $excludingId): bool
    {
        $sql = 'SELECT 1 FROM `user` WHERE `' . $column . '` = :value';
        $parameters = [':value' => $value];
        if ($excludingId !== null) {
            $sql .= ' AND `id` <> :id';
            $parameters[':id'] = $excludingId;
        }
        $sql .= ' LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }
}
