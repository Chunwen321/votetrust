<?php

// PIC: Low Wen Kai - Election Management Module

namespace App\Services;

use Carbon\CarbonImmutable;

final class ApiEnvelope
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function success(string $requestId, array $data, string $message = 'Request completed successfully.'): array
    {
        return [
            'status' => 'S',
            'message' => $message,
            'requestID' => $requestId,
            'timestamp' => CarbonImmutable::now()->toAtomString(),
            'data' => $data,
        ];
    }

    /** @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public static function failure(
        string $requestId,
        string $message,
        array $details = [],
        string $status = 'F',
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'requestID' => $requestId,
            'timestamp' => CarbonImmutable::now()->toAtomString(),
            'errors' => $details,
        ];
    }
}
