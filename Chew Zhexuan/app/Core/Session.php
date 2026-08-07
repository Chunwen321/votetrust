<?php

declare(strict_types=1);

namespace VoteTrust\UserAdmin\Core;

final class Session
{
    public static function start(string $moduleRoot, string $configuredPath): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $storagePath = self::resolvePath($moduleRoot, $configuredPath);
        if (!is_dir($storagePath) && !mkdir($storagePath, 0700, true) && !is_dir($storagePath)) {
            throw new \RuntimeException('Unable to create the configured session directory.');
        }

        session_save_path($storagePath);
        session_name((string) Config::get('SESSION_NAME', 'VOTETRUST_ELECTION'));
        session_set_cookie_params([
            'httponly' => true,
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function consumeFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_unset();
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'] ?? '', (bool) $parameters['secure'], (bool) $parameters['httponly']);
        }
        session_destroy();
    }

    private static function resolvePath(string $moduleRoot, string $configuredPath): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1 || str_starts_with($configuredPath, '/')) {
            return $configuredPath;
        }

        return $moduleRoot . '/' . $configuredPath;
    }
}
