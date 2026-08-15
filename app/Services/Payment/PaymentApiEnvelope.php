<?php

// PIC: Risky Puah Kaho - Payment Module

namespace App\Services\Payment;

/** Builds the Payment module's Interface Agreement response envelope. */
final class PaymentApiEnvelope
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function success(string $requestId, array $data, string $message): array
    {
        return [
            'status' => 'S',
            'message' => $message,
            'requestID' => $requestId,
            'timestamp' => now()->toAtomString(),
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, mixed>
     */
    public static function failure(
        string $requestId,
        string $message,
        array $errors = [],
        string $status = 'F',
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'requestID' => $requestId,
            'timestamp' => now()->toAtomString(),
            'errors' => $errors,
        ];
    }
}
