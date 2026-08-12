<?php

// PIC: Chew Zhexuan — receives Didit-hosted eKYC outcomes for the User module.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EkycVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DiditWebhookController extends Controller
{
    public function __construct(private readonly EkycVerificationService $ekyc) {}

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if (! is_array($payload)) {
            abort(400, 'The Didit webhook payload must be JSON.');
        }

        // Didit V3 signs canonical JSON. The service validates X-Signature-V2 and the timestamp before any state change.
        $this->ekyc->processDiditWebhook(
            $payload,
            (string) $request->header('X-Timestamp', ''),
            (string) $request->header('X-Signature-V2', ''),
        );

        return response()->json(['ok' => true]);
    }
}
