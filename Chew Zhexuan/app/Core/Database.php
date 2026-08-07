<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Core;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = (string) Config::get('DB_HOST', '127.0.0.1');
        $port = (string) Config::get('DB_PORT', '3306');
        $database = (string) Config::get('DB_DATABASE', 'election_db');
        $charset = (string) Config::get('DB_CHARSET', 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        self::$connection = new PDO(
            $dsn,
            (string) Config::get('DB_USERNAME', 'root'),
            (string) Config::get('DB_PASSWORD', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$connection;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);
            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
