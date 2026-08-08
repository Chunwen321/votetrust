<?php

// PIC: Low Wen Kai - Election Management Module

return [
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
