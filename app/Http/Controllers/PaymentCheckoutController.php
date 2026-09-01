<?php

// PIC: Risky Puah Kaho - Payment Module

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayContract;
use App\Models\Election;
use App\Models\Payment;
use App\Services\ElectionService;
use App\Support\PublicElectionExpiry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A convincing but entirely fake checkout page for the IDontPay99 mock
 * gateway. No card data is ever persisted, logged, or transmitted — the
 * card number is only inspected in-memory to pick a demo outcome, the same
 * way real sandbox gateways use "magic" test card numbers.
 */
final class PaymentCheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayContract $gateway,
        private readonly ElectionService $elections,
    ) {}

    public function show(Request $request, int $payment): View|RedirectResponse
    {
        $record = Payment::query()->findOrFail($payment);
        $this->authorizeAccess($request, $record);
        if ($redirect = $this->expireHostingElection($request, $record, 'payment_checkout_view')) {
            return $redirect;
        }

        return view('payments.checkout', ['payment' => $record]);
    }

    public function receipt(Request $request, int $payment): View
    {
        // election_id is only ever set for ELECTION_HOSTING payments (see
        // payForElection() below). CANDIDATE_DEPOSIT payments leave it null —
        // the Candidate module tracks that association on its own Candidacy
        // row instead — so the receipt's Election field renders blank for
        // deposits. Enriching it would mean reaching into Candidacy, which is
        // the Candidate module's data; left as a known gap for that module
        // to close if it wants receipts to show the election.
        $record = Payment::query()->with('election')->findOrFail($payment);
        $this->authorizeAccess($request, $record);

        return view('payments.receipt', ['payment' => $record]);
    }

    public function charge(Request $request, int $payment): RedirectResponse
    {
        $record = Payment::query()->findOrFail($payment);
        $this->authorizeAccess($request, $record);
        if ($redirect = $this->expireHostingElection($request, $record, 'payment_checkout_charge')) {
            return $redirect;
        }

        $request->validate([
            'card_number' => ['required', 'string', 'regex:/^[0-9 ]{12,23}$/'],
            'card_holder' => ['required', 'string', 'max:100'],
            'expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/[0-9]{2}$/'],
            'cvv' => ['required', 'string', 'regex:/^[0-9]{3,4}$/'],
        ]);

        if ($record->paymentStatus()->isTerminal()) {
            return redirect()->route('payments.checkout.show', $record)
                ->with('success', 'This payment was already settled.');
        }

        // A card number ending in "0002" is IDontPay99's "always declines"
        // test card, mirroring how real sandbox gateways expose magic
        // numbers for testing both outcomes. Nothing about the card is kept.
        // The "simulate failure" checkbox is a more direct way to force the
        // same outcome without needing to remember the magic card number.
        $cardNumber = (string) $request->input('card_number');
        $declines = $request->boolean('simulate_failure')
            || str_ends_with(preg_replace('/\s+/', '', $cardNumber), '0002');

        // The regex above only checks MM/YY format; a syntactically valid
        // expiry can still be in the past, so decline those the same way a
        // real gateway would reject an expired card.
        [$expiryMonth, $expiryYear] = array_map('intval', explode('/', (string) $request->input('expiry')));
        $expiresAt = CarbonImmutable::create(2000 + $expiryYear, $expiryMonth, 1)->endOfMonth();
        $expired = CarbonImmutable::now()->greaterThan($expiresAt);

        if ($expired) {
            $this->gateway->fail($record, 'IDontPay99 test card has expired.');
        } elseif ($declines) {
            $this->gateway->fail($record, 'IDontPay99 test card declined the charge.');
        } else {
            $this->gateway->confirm($record);
        }

        return redirect()->route('payments.checkout.show', $record);
    }

    /**
     * Election-module-agnostic entry point: resolves (or opens) the hosting
     * Payment for an election and hands the organizer straight to the same
     * checkout page candidates use. Reads the shared Election model only —
     * no Election-owned controller, route, or view is touched. The Payment API
     * later resolves the settled transaction by election ID, so the organizer
     * never needs to copy a provider reference between modules.
     */
    public function payForElection(Request $request, int $election): RedirectResponse
    {
        $record = Election::query()->findOrFail($election);

        $userId = (int) $request->user()?->getAuthIdentifier();
        if ((int) $record->organizer_id !== $userId) {
            throw new AuthorizationException('You do not have access to this election\'s payment.');
        }

        if ($record->status !== Election::PENDING_PAYMENT) {
            throw new AuthorizationException('This election is not currently awaiting hosting payment.');
        }

        $expired = $this->elections->expireOverduePublicElection(
            $request->user(),
            (int) $record->getKey(),
            'payment_checkout_open',
        );
        if ($expired instanceof Election) {
            return redirect()->route('elections.show', $expired)
                ->with('error', PublicElectionExpiry::MESSAGE);
        }

        $payment = Payment::query()
            ->where('purpose', Payment::PURPOSE_ELECTION_HOSTING)
            ->where('election_id', $record->getKey())
            ->whereIn('status', ['Pending', 'Paid'])
            ->latest('id')
            ->first();

        if (! $payment instanceof Payment) {
            $payment = Payment::query()->create([
                'amount' => $record->hosting_fee,
                'currency' => $record->currency,
                'status' => 'Pending',
                'gateway' => 'idontpay99',
                'organizer_id' => $record->organizer_id,
                'election_id' => $record->getKey(),
                'purpose' => Payment::PURPOSE_ELECTION_HOSTING,
                'provider_reference' => 'VT-HOST-'.strtoupper(Str::random(10)),
            ]);
        }

        return redirect()->route('payments.checkout.show', $payment);
    }

    private function expireHostingElection(
        Request $request,
        Payment $payment,
        string $trigger,
    ): ?RedirectResponse {
        if ($payment->purpose !== Payment::PURPOSE_ELECTION_HOSTING || $payment->election_id === null) {
            return null;
        }

        $expired = $this->elections->expireOverduePublicElection(
            $request->user(),
            (int) $payment->election_id,
            $trigger,
        );
        if (! $expired instanceof Election) {
            return null;
        }

        return redirect()->route('elections.show', $expired)
            ->with('error', PublicElectionExpiry::MESSAGE);
    }

    private function authorizeAccess(Request $request, Payment $payment): void
    {
        $userId = (int) $request->user()?->getAuthIdentifier();
        $owner = $payment->candidate_id ?? $payment->organizer_id;

        if ($owner === null || (int) $owner !== $userId) {
            throw new AuthorizationException('You do not have access to this payment.');
        }
    }
}
