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

    'ekyc' => [
        // The local adapter is for development only. Configure "didit" for the hosted document/selfie flow.
        'driver' => (string) env('EKYC_DRIVER', 'local'),
        'url' => (string) env('EKYC_PROVIDER_URL', ''),
        'api_key' => (string) env('EKYC_PROVIDER_API_KEY', ''),
        'connect_timeout_seconds' => (int) env('EKYC_PROVIDER_CONNECT_TIMEOUT_SECONDS', 2),
        'timeout_seconds' => (int) env('EKYC_PROVIDER_TIMEOUT_SECONDS', 10),
        'response_max_age_seconds' => (int) env('EKYC_PROVIDER_RESPONSE_MAX_AGE_SECONDS', 300),
        'didit' => [
            'base_url' => (string) env('DIDIT_BASE_URL', 'https://verification.didit.me'),
            'api_key' => (string) env('DIDIT_API_KEY', ''),
            'workflow_id' => (string) env('DIDIT_WORKFLOW_ID', ''),
            'webhook_secret' => (string) env('DIDIT_WEBHOOK_SECRET', ''),
            'webhook_tolerance_seconds' => (int) env('DIDIT_WEBHOOK_TOLERANCE_SECONDS', 300),
        ],
    ],

    'services' => [
        'election' => [
            'base_url' => (string) env('ELECTION_SERVICE_BASE_URL', env('APP_URL', '')),
            'api_key' => (string) env('ELECTION_SERVICE_API_KEY', env('ELECTION_API_KEY', '')),
            'connect_timeout_seconds' => (int) env('ELECTION_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('ELECTION_SERVICE_TIMEOUT_SECONDS', 5),
            'response_max_age_seconds' => (int) env('API_RESPONSE_MAX_AGE_SECONDS', 300),
        ],
        'candidate' => [
            'base_url' => (string) env('CANDIDATE_SERVICE_BASE_URL', env('APP_URL', '')),
            'api_key' => (string) env('CANDIDATE_SERVICE_API_KEY', env('CANDIDATE_API_KEY', '')),
            'connect_timeout_seconds' => (int) env('CANDIDATE_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('CANDIDATE_SERVICE_TIMEOUT_SECONDS', 5),
            'response_max_age_seconds' => (int) env('API_RESPONSE_MAX_AGE_SECONDS', 300),
        ],
        'voting' => [
            'base_url' => (string) env('VOTING_SERVICE_BASE_URL', env('APP_URL', '')),
            'api_key' => (string) env('VOTING_SERVICE_API_KEY', env('VOTING_API_KEY', '')),
            'connect_timeout_seconds' => (int) env('VOTING_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('VOTING_SERVICE_TIMEOUT_SECONDS', 5),
            'response_max_age_seconds' => (int) env('API_RESPONSE_MAX_AGE_SECONDS', 300),
        ],
    ],
];
