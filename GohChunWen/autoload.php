<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'VoteTrust\\Voting\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
        return;
    }

    $aggregateFiles = [
        'Model\\VoteStorage' => '/Model/VoteORM.php',
        'Model\\VoteAlreadyExists' => '/Model/VoteORM.php',
        'Service\\VoteCastingService' => '/Service/VoteService.php',
        'Service\\BasicVoteService' => '/Service/VoteService.php',
        'Service\\VoteServiceDecorator' => '/Service/VoteService.php',
        'Service\\AuthorizationDecorator' => '/Service/VoteService.php',
        'Service\\DuplicateVoteDecorator' => '/Service/VoteService.php',
        'Service\\VoteAccessDenied' => '/Service/VoteService.php',
    ];

    if (isset($aggregateFiles[$relative])) {
        require_once __DIR__ . $aggregateFiles[$relative];
    }
});

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
