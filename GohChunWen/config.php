<?php

declare(strict_types=1);

return [
    'app' => [
        'base_url' => rtrim(getenv('VOTING_APP_BASE_URL') ?: '', '/'),
        'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Kuala_Lumpur',
    ],
    'database' => [
        'dsn' => getenv('VOTING_DB_DSN') ?: 'mysql:host=localhost;dbname=election_db;charset=utf8mb4',
        'user' => getenv('VOTING_DB_USER') ?: 'root',
        'password' => getenv('VOTING_DB_PASSWORD') ?: '',
    ],
    'api' => [
        'election_url' => rtrim(getenv('ELECTION_API_URL') ?: 'http://127.0.0.1:8080', '/'),
        'user_url' => rtrim(getenv('USER_API_URL') ?: '', '/'),
        'consumer_key' => getenv('ELECTION_API_KEY') ?: '',
        'provider_key' => getenv('VOTING_API_KEY') ?: '',
    ],
    'session' => [
        'name' => getenv('VOTING_SESSION_NAME') ?: 'VOTETRUST_ELECTION',
        'path' => getenv('VOTING_SESSION_PATH') ?: dirname(__DIR__) . '/LowWenKai/storage/sessions',
    ],
];
