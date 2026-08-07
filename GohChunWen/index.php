<?php

declare(strict_types=1);

use VoteTrust\Voting\Controller\VotingController;
use VoteTrust\Voting\Model\VoteORM;
use VoteTrust\Voting\Service\AuthorizationDecorator;
use VoteTrust\Voting\Service\BasicVoteService;
use VoteTrust\Voting\Service\DuplicateVoteDecorator;
use VoteTrust\Voting\Service\ExternalApi;

require_once __DIR__ . '/autoload.php';

$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (getenv($key) === false) {
            putenv($key . '=' . trim($value, "\"'"));
        }
    }
}

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['app']['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['session']['name']);
    if (is_dir($config['session']['path'])) {
        session_save_path($config['session']['path']);
    }
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start(['use_strict_mode' => true]);
}

header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

$pdo = new PDO(
    $config['database']['dsn'],
    $config['database']['user'],
    $config['database']['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$voteORM = new VoteORM($pdo);
$externalApi = new ExternalApi(
    $pdo,
    $config['api']['election_url'],
    $config['api']['user_url'],
    $config['api']['consumer_key'],
);
$service = new AuthorizationDecorator(
    new DuplicateVoteDecorator(new BasicVoteService($voteORM), $voteORM),
    $externalApi,
);
$controller = new VotingController(
    $service,
    $voteORM,
    $externalApi,
    $pdo,
    $config['api']['provider_key'],
    __DIR__ . '/View',
    $config['app']['base_url'],
);

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

match (true) {
    $method === 'GET' && ($path === '/' || str_ends_with($path, '/voting')) => $controller->index(),
    $method === 'GET' && str_ends_with($path, '/voting/ballot') => $controller->ballot(),
    $method === 'POST' && str_ends_with($path, '/voting/cast') => $controller->cast(),
    $method === 'GET' && str_ends_with($path, '/api/v1/votes/status') => $controller->statusApi(),
    $method === 'GET' && str_ends_with($path, '/api/v1/votes/tally') => $controller->tallyApi(),
    default => (static function (): void {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'F',
            'message' => 'Voting route not found.',
            'requestID' => 'UNKNOWN',
            'timestamp' => date(DATE_ATOM),
            'errors' => [],
        ], JSON_UNESCAPED_SLASHES);
    })(),
};
