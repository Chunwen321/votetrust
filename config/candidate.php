<?php

// PIC: Wong Shun Bin — Candidate Registration module configuration.
$localEnvironment = (string) env('APP_ENV', 'production') === 'local';

return [
    'disk' => env('CANDIDATE_STORAGE_DISK', 'local'),
    'photos_directory' => env('CANDIDATE_PHOTOS_DIRECTORY', 'candidate/photos'),
    'deposit_amount' => env('CANDIDATE_DEPOSIT_AMOUNT', '10.00'),
    'max_photo_kilobytes' => (int) env('CANDIDATE_MAX_PHOTO_KILOBYTES', 2048),

    // PIC: Wong Shun Bin - REST provider used by Payment to retrieve candidate/payee details.
    'api' => [
        'key' => env('CANDIDATE_API_KEY')
            ?: env('CANDIDATE_SERVICE_API_KEY')
            ?: ($localEnvironment ? 'votetrust-candidate-local-2026' : ''),
        'clock_skew_seconds' => (int) env('CANDIDATE_API_CLOCK_SKEW_SECONDS', 300),
    ],

    // PIC: Wong Shun Bin - Empty Payment credentials deliberately fail closed.
    'services' => [
        'payment' => [
            'base_url' => env('CANDIDATE_PAYMENT_API_BASE_URL'),
            'api_key' => env('CANDIDATE_PAYMENT_API_KEY'),
            'connect_timeout_seconds' => (int) env('CANDIDATE_PAYMENT_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('CANDIDATE_PAYMENT_TIMEOUT_SECONDS', 5),
            'response_max_age_seconds' => (int) env('CANDIDATE_PAYMENT_RESPONSE_MAX_AGE_SECONDS', 300),
        ],
        'voting' => [
            'base_url' => env('VOTING_SERVICE_BASE_URL', env('APP_URL', '')),
            'api_key' => env('VOTING_SERVICE_API_KEY')
                ?: env('VOTING_API_KEY')
                ?: ($localEnvironment ? 'votetrust-voting-local-2026' : ''),
            'connect_timeout_seconds' => (int) env('VOTING_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('VOTING_SERVICE_TIMEOUT_SECONDS', 5),
            'response_max_age_seconds' => (int) env('API_RESPONSE_MAX_AGE_SECONDS', 300),
        ],
    ],
];
