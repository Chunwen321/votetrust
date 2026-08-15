<?php

// PIC: Risky Puah Kaho - Payment Module

return [
    // Selects the PaymentGatewayContract implementation bound in AppServiceProvider.
    'gateway' => env('PAYMENT_GATEWAY', 'mock'),

    // Validates inbound calls from the Election module to /api/v1/payments/verify.
    'api' => [
        'key' => env('PAYMENT_API_KEY'),
        'clock_skew_seconds' => (int) env('PAYMENT_API_RESPONSE_CLOCK_SKEW_SECONDS', 300),
    ],

    // Validates inbound calls from the Candidate module to /api/v1/payments/{id}/status.
    'candidate_api' => [
        'key' => env('CANDIDATE_PAYMENT_API_KEY'),
    ],

    // Empty credentials deliberately fail closed: Payment pulls, never trusts, this data.
    'services' => [
        'candidate' => [
            'base_url' => env('PAYMENT_CANDIDATE_API_BASE_URL', env('APP_URL', '')),
            'api_key' => env('CANDIDATE_API_KEY'),
            'connect_timeout_seconds' => (int) env('PAYMENT_CANDIDATE_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('PAYMENT_CANDIDATE_TIMEOUT_SECONDS', 5),
            'response_max_age_seconds' => (int) env('PAYMENT_CANDIDATE_RESPONSE_MAX_AGE_SECONDS', 300),
        ],
        'election' => [
            'base_url' => env('PAYMENT_ELECTION_API_BASE_URL', env('APP_URL', '')),
            'api_key' => env('ELECTION_API_KEY'),
            'connect_timeout_seconds' => (int) env('PAYMENT_ELECTION_CONNECT_TIMEOUT_SECONDS', 2),
            'timeout_seconds' => (int) env('PAYMENT_ELECTION_TIMEOUT_SECONDS', 5),
            'response_max_age_seconds' => (int) env('PAYMENT_ELECTION_RESPONSE_MAX_AGE_SECONDS', 300),
        ],
    ],
];
