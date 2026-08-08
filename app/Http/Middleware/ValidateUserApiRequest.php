<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ValidateUserApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('votetrust-user.api.key', '');
        $providedKey = (string) ($request->header('X-User-Api-Key')
            ?: $request->header('X-Internal-Api-Key', ''));

        if ($configuredKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return $this->failure($request, 'The User API key is invalid.', 403);
        }

        try {
            $requestedAt = CarbonImmutable::parse((string) $request->input('timestamp'));
        } catch (Throwable) {
            return $this->failure($request, 'The request timestamp must be valid ISO 8601.', 422);
        }

        $maximumAge = max(1, (int) config('votetrust-user.api.max_age_seconds', 300));
        if (abs(CarbonImmutable::now()->diffInSeconds($requestedAt, false)) > $maximumAge) {
            return $this->failure($request, 'The request timestamp is outside the permitted window.', 422);
        }

        return $next($request);
    }

    private function failure(Request $request, string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'E',
            'message' => $message,
            'requestID' => (string) $request->input('requestID', 'UNKNOWN'),
            'timestamp' => now()->toAtomString(),
            'errors' => [],
        ], $status);
    }
}
