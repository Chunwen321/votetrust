<?php

// PIC: Risky Puah Kaho - Payment Module

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Side-effect-free liveness probe for the Payment module: proves the route
 * is reachable, the caller's API key is valid, and the bound gateway
 * resolves, without writing anything. Used by the admin health page.
 */
final class PaymentPingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $requestId = (string) $request->query('requestID', 'ping_'.Str::random(12));

        $configuredKey = (string) config('payment.api.key', '');
        $providedKey = (string) $request->header('X-API-Key', '');
        if ($configuredKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json(
                PaymentApiEnvelope::failure($requestId, 'The Payment API key is invalid.'),
                401,
            );
        }

        return response()->json(PaymentApiEnvelope::success($requestId, [
            'gateway' => class_basename(app(PaymentGatewayContract::class)),
        ], 'Payment module is reachable.'));
    }
}
