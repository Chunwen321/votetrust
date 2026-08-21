<?php

// PIC: Low Wen Kai - Election Management Module

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ApiEnvelope;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateElectionApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = trim((string) config('election.api.key', ''));
        $providedKey = (string) $request->header('X-Internal-Api-Key', '');

        if ($configuredKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return $this->failure($request);
        }

        return $next($request);
    }

    private function failure(Request $request): JsonResponse
    {
        return response()->json(ApiEnvelope::failure(
            (string) $request->input('requestID', 'UNKNOWN'),
            'Election API access denied.',
        ), 403);
    }
}
