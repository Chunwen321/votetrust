<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'VoteTrust\\UserAdmin\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

require_once __DIR__ . '/app/Support/helpers.php';
