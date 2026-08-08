<?php

// PIC: Goh Chun Wen - Voting Process module configuration.
return [
    'api_key' => env('VOTING_API_KEY'),
    'request_max_age_seconds' => (int) env('API_REQUEST_MAX_AGE_SECONDS', 300),
    'response_max_age_seconds' => (int) env('API_RESPONSE_MAX_AGE_SECONDS', 300),
    'lock_seconds' => (int) env('VOTING_LOCK_SECONDS', 10),
    'lock_wait_seconds' => (int) env('VOTING_LOCK_WAIT_SECONDS', 3),

    'services' => [
        'election' => [
            'base_url' => env('ELECTION_SERVICE_BASE_URL', env('APP_URL')),
            'api_key' => env('ELECTION_SERVICE_API_KEY'),
            'connect_timeout_seconds' => (int) env('ELECTION_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('ELECTION_SERVICE_TIMEOUT_SECONDS', 5),
        ],
        'user' => [
            'base_url' => env('USER_SERVICE_BASE_URL', env('APP_URL')),
            'api_key' => env('USER_SERVICE_API_KEY', env('USER_API_KEY')),
            'connect_timeout_seconds' => (int) env('USER_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('USER_SERVICE_TIMEOUT_SECONDS', 5),
        ],
    ],
];
