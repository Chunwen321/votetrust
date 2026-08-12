<?php

// PIC: Wong Shun Bin — shared request authentication for Candidate module APIs.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CandidateRegistration\CandidateApiEnvelope;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ValidateCandidateServiceRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('candidate.api.key', '');
        $providedKey = (string) ($request->header('X-Candidate-Api-Key') ?: $request->header('X-Internal-Api-Key', ''));
        if ($configuredKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return $this->failure($request, 'The Candidate API key is invalid.', 401);
        }

        $input = $request->isMethod('GET') ? $request->query() : $request->json()->all();
        $validator = Validator::make($input, [
            'requestID' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'timestamp' => ['required', 'string'],
        ]);
        $validator->after(function ($validator) use ($input): void {
            try {
                $requestedAt = CarbonImmutable::parse((string) ($input['timestamp'] ?? ''));
                if (abs(CarbonImmutable::now()->diffInSeconds($requestedAt, false)) > max(1, (int) config('candidate.api.clock_skew_seconds', 300))) {
                    $validator->errors()->add('timestamp', 'The request timestamp is outside the permitted window.');
                }
            } catch (Throwable) {
                $validator->errors()->add('timestamp', 'The request timestamp must be valid ISO 8601.');
            }
        });

        if ($validator->fails()) {
            return $this->failure($request, 'The Candidate API request is invalid.', 422, $validator->errors()->toArray());
        }

        return $next($request);
    }

    /** @param array<string, mixed> $errors */
    private function failure(Request $request, string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json(CandidateApiEnvelope::failure(
            (string) ($request->input('requestID') ?? 'UNKNOWN'),
            $message,
            $errors,
        ), $status);
    }
}
