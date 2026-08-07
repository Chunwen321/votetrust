<?php

declare(strict_types=1);

use VoteTrust\UserAdmin\Core\Config;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) Config::get('APP_BASE_URL', ''), '/');
        $normalized = '/' . ltrim($path, '/');

        return $base . ($normalized === '/' ? '/' : $normalized);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $values = $_SESSION['_old_input'] ?? [];

        return $values[$key] ?? $default;
    }
}
