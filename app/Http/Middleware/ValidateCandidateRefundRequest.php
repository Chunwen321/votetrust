<?php

namespace App\Http\Middleware;

use App\Services\Payment\PaymentApiEnvelope;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Authenticates and validates Candidate-module deposit refund requests. */
final class ValidateCandidateRefundRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('payment.candidate_api.key', '');
        $providedKey = (string) $request->header('X-Payment-Api-Key', '');
        if ($configuredKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return $this->failure($request, 'The Payment API key is invalid.', 401);
        }

        $input = (array) $request->json()->all();
        $validator = Validator::make($input, [
            'candidateId' => ['required', 'integer', 'min:1'],
            'electionId' => ['required', 'integer', 'min:1'],
            'candidateVotes' => ['required', 'integer', 'min:0'],
            'totalVotes' => ['required', 'integer', 'min:1'],
            'requestID' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'],
            'timestamp' => ['required', 'string'],
        ]);
        $validator->after(function ($validator) use ($input): void {
            try {
                $requestedAt = CarbonImmutable::parse((string) ($input['timestamp'] ?? ''));
                if (abs(now()->diffInSeconds($requestedAt, false)) > max(1, (int) config('payment.api.clock_skew_seconds', 300))) {
                    $validator->errors()->add('timestamp', 'The request timestamp is outside the permitted window.');
                }
            } catch (Throwable) {
                $validator->errors()->add('timestamp', 'The request timestamp must be valid ISO 8601.');
            }
        });

        if ($validator->fails()) {
            return $this->failure($request, 'The candidate refund request is invalid.', 422, $validator->errors()->toArray());
        }

        return $next($request);
    }

    /** @param array<string, mixed> $errors */
    private function failure(Request $request, string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json(PaymentApiEnvelope::failure(
            (string) $request->input('requestID', 'UNKNOWN'),
            $message,
            $errors,
        ), $status);
    }
}
