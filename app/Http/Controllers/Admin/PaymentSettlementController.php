<?php

// PIC: Risky Puah Kaho - Payment Module

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentFacade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Lets an administrator settle a mock payment (confirm/fail/refund/cancel) for testing and support. */
final class PaymentSettlementController extends Controller
{
    public function __construct(private readonly PaymentFacade $payments) {}

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $input = $request->validate([
            'action' => ['required', 'string', 'in:confirm,fail,refund,cancel'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $reason = trim((string) ($input['reason'] ?? ''));

        try {
            match ($input['action']) {
                'confirm' => $this->payments->confirm($payment),
                'fail' => $this->payments->fail($payment, $reason !== '' ? $reason : 'Marked failed by administrator.'),
                'refund' => $this->payments->refund($payment, $reason !== '' ? $reason : 'Refunded by administrator.'),
                'cancel' => $this->payments->cancel($payment, $reason !== '' ? $reason : 'Cancelled by administrator.'),
            };
        } catch (BusinessRuleException $exception) {
            throw ValidationException::withMessages(['action' => $exception->getMessage()]);
        }

        return back()->with('status', 'Payment '.$payment->status.'.');
    }
}
