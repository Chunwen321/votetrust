<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

// PIC: Goh Chun Wen - Access control and request-envelope validation for Voting APIs.
final class ValidateVotingApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('voting.api_key', '');
        $providedKey = (string) $request->header('X-Internal-Api-Key', '');

        if ($configuredKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return $this->failure($request, 'Voting API access denied.', 403);
        }

        $validator = Validator::make($request->query(), [
            'requestID' => ['bail', 'required', 'string', 'regex:/\A[A-Za-z0-9_-]{8,64}\z/'],
            'timestamp' => ['bail', 'required', 'string', 'date_format:Y-m-d\TH:i:sP'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if ($validator->errors()->has('timestamp')) {
                return;
            }

            try {
                $requestedAt = CarbonImmutable::parse((string) $request->query('timestamp'));
            } catch (Throwable) {
                $validator->errors()->add('timestamp', 'The request timestamp must be valid ISO 8601.');

                return;
            }

            if (abs(now()->diffInSeconds($requestedAt, false))
                > max(1, (int) config('voting.request_max_age_seconds', 300))) {
                $validator->errors()->add('timestamp', 'The request timestamp is outside the permitted window.');
            }
        });

        if ($validator->fails()) {
            return $this->failure(
                $request,
                'The Voting API request is invalid.',
                422,
                $validator->errors()->toArray(),
            );
        }

        return $next($request);
    }

    /** @param array<string, mixed> $errors */
    private function failure(
        Request $request,
        string $message,
        int $httpStatus,
        array $errors = [],
    ): JsonResponse {
        return response()->json([
            'status' => 'F',
            'message' => $message,
            'requestID' => (string) $request->query('requestID', 'UNKNOWN'),
            'timestamp' => now()->toAtomString(),
            'errors' => $errors,
        ], $httpStatus);
    }
}
