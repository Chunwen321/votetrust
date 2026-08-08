<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

return [
    'auth' => [
        'max_failed_attempts' => (int) env('AUTH_MAX_FAILED_ATTEMPTS', 5),
    ],

    'api' => [
        'key' => (string) env('USER_API_KEY', ''),
        'max_age_seconds' => (int) env('API_REQUEST_MAX_AGE_SECONDS', 300),
    ],

    'services' => [
        'voting' => [
            'base_url' => (string) env('VOTING_SERVICE_BASE_URL', env('APP_URL', '')),
            'api_key' => (string) env('VOTING_SERVICE_API_KEY', env('VOTING_API_KEY', '')),
            'connect_timeout_seconds' => (int) env('VOTING_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('VOTING_SERVICE_TIMEOUT_SECONDS', 5),
            'response_max_age_seconds' => (int) env('API_RESPONSE_MAX_AGE_SECONDS', 300),
        ],
    ],
];
