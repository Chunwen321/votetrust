<?php

// PIC: Goh Chun Wen - Voting Process module configuration.
$localEnvironment = (string) env('APP_ENV', 'production') === 'local';

return [
    'api_key' => env('VOTING_API_KEY')
        ?: env('VOTING_SERVICE_API_KEY')
        ?: ($localEnvironment ? 'votetrust-voting-local-2026' : ''),
    'request_max_age_seconds' => (int) env('API_REQUEST_MAX_AGE_SECONDS', 300),
    'response_max_age_seconds' => (int) env('API_RESPONSE_MAX_AGE_SECONDS', 300),
    'lock_seconds' => (int) env('VOTING_LOCK_SECONDS', 10),
    'lock_wait_seconds' => (int) env('VOTING_LOCK_WAIT_SECONDS', 3),
    'cast_rate_limit_per_minute' => (int) env('VOTING_CAST_RATE_LIMIT_PER_MINUTE', 10),

    'services' => [
        'election' => [
            'base_url' => env('ELECTION_SERVICE_BASE_URL', env('APP_URL')),
            'api_key' => env('ELECTION_SERVICE_API_KEY')
                ?: env('ELECTION_API_KEY')
                ?: ($localEnvironment ? 'votetrust-election-local-2026' : ''),
            'connect_timeout_seconds' => (int) env('ELECTION_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('ELECTION_SERVICE_TIMEOUT_SECONDS', 5),
        ],
        'user' => [
            'base_url' => env('USER_SERVICE_BASE_URL', env('APP_URL')),
            'api_key' => env('USER_SERVICE_API_KEY')
                ?: env('USER_API_KEY')
                ?: ($localEnvironment ? 'votetrust-user-local-2026' : ''),
            'connect_timeout_seconds' => (int) env('USER_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('USER_SERVICE_TIMEOUT_SECONDS', 5),
        ],
    ],
];
