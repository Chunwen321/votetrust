<?php

// PIC: Risky Puah Kaho - Payment Module

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\PaymentGatewayContract;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/** Read-only diagnostic page: proves the Payment module's own API is reachable and working. */
final class PaymentHealthController extends Controller
{
    public function __construct(private readonly PaymentGatewayContract $gateway) {}

    public function show(): View
    {
        $endpoint = rtrim((string) config('app.url'), '/').'/api/v1/payments/ping';
        $apiKey = (string) config('payment.api.key', '');

        $reachable = false;
        $keyValid = false;
        $gateway = null;
        $error = null;

        if ($apiKey === '') {
            $error = 'PAYMENT_API_KEY is not configured, so no caller could authenticate.';
        } else {
            try {
                $response = Http::acceptJson()
                    ->withHeaders(['X-API-Key' => $apiKey])
                    ->connectTimeout(2)
                    ->timeout(3)
                    ->get($endpoint);

                $reachable = true;
                $keyValid = $response->status() === 200 && ($response->json('status') === 'S');
                $gateway = $response->json('data.gateway');

                if (! $keyValid) {
                    $error = $response->json('message', 'The ping endpoint rejected the request.');
                }
            } catch (ConnectionException $exception) {
                $error = 'Could not connect to '.$endpoint.': '.$exception->getMessage();
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        if ($gateway === null) {
            // Fall back to resolving the binding in-process so the page still
            // shows something useful even when the live HTTP probe failed.
            $gateway = class_basename($this->gateway);
        }

        $recentPayments = Payment::query()
            ->with(['organizer', 'candidate'])
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.payments.health', [
            'endpoint' => $endpoint,
            'reachable' => $reachable,
            'keyValid' => $keyValid,
            'gateway' => $gateway,
            'error' => $error,
            'recentPayments' => $recentPayments,
        ]);
    }

    /** Fires a throwaway mock transaction through the real gateway and flashes the result. */
    public function runTest(Request $request): RedirectResponse
    {
        $simulateFailure = $request->boolean('simulate_failure');
        $reference = 'VT-TEST-'.strtoupper(Str::random(8)).($simulateFailure ? '-FAIL' : '');

        $payment = Payment::query()->create([
            'amount' => '1.00',
            'currency' => 'MYR',
            'status' => 'Pending',
            'gateway' => 'mock',
            'purpose' => Payment::PURPOSE_SYSTEM_TEST,
            'provider_reference' => $reference,
            'request_id' => 'admin_test_'.Str::lower(Str::random(12)),
        ]);

        $settlement = $this->gateway->resolveStatus($payment);

        return redirect()
            ->route('admin.payments.health')
            ->with('payment_test_result', [
                'paymentId' => $payment->getKey(),
                'reference' => $reference,
                'status' => $settlement->status->value,
                'ranAt' => $settlement->occurredAt->format('d M Y, g:i:s A'),
            ]);
    }
}
