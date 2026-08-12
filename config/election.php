<?php

// PIC: Low Wen Kai - Election Management Module

return [
    'api' => [
        'key' => env('ELECTION_API_KEY', env('ELECTION_SERVICE_API_KEY', '')),
    ],

    // Election never reads or writes User/Voter tables directly. Private
    // roster matching and enrolment are delegated to this User Module API.
    'user_service' => [
        'base_url' => env('USER_SERVICE_BASE_URL', env('APP_URL', '')),
        'api_key' => env('USER_SERVICE_API_KEY', env('USER_API_KEY', '')),
        'connect_timeout_seconds' => (int) env('USER_SERVICE_CONNECT_TIMEOUT_SECONDS', 2),
        'timeout_seconds' => (int) env('USER_SERVICE_TIMEOUT_SECONDS', 10),
        'response_max_age_seconds' => (int) env('API_RESPONSE_MAX_AGE_SECONDS', 300),
    ],

    'payment' => [
        'url' => env('PAYMENT_API_URL', 'http://127.0.0.1:8081/api/v1/payments/verify'),
        'api_key' => env('PAYMENT_API_KEY'),
        'timeout' => (int) env('PAYMENT_API_TIMEOUT', 4),
        'response_clock_skew_seconds' => (int) env('PAYMENT_API_RESPONSE_CLOCK_SKEW_SECONDS', 300),
    ],

    'admin_api_key' => env('ADMIN_API_KEY'),
    'security_log_channel' => env('ELECTION_SECURITY_LOG_CHANNEL', 'stack'),
    'api_clock_skew_seconds' => (int) env('ELECTION_API_CLOCK_SKEW_SECONDS', 300),
];
