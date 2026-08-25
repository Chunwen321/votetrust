{{-- PIC: Risky Puah Kaho - Payment Module --}}
@extends('layouts.app')

@section('title', 'Secure checkout')

@section('content')
<div class="payment-checkout-shell">
    <div class="payment-checkout-card">
        <div class="payment-checkout-brand">
            <span class="payment-checkout-logo">IDontPay99</span>
            <span class="payment-checkout-badge">TEST MODE — no real charge</span>
        </div>

        <div class="payment-checkout-summary">
            <span>Amount due</span>
            <strong>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</strong>
            <small>Payment #{{ $payment->id }} &middot; {{ $payment->purpose ? str_replace('_', ' ', $payment->purpose) : 'Payment' }}</small>
        </div>

        @php $status = $payment->paymentStatus(); @endphp

        @if($status->isTerminal())
            <div class="payment-checkout-result payment-checkout-result--{{ strtolower($status->value) }}">
                <h2>{{ $status->value === 'Paid' ? 'Payment successful' : $status->value }}</h2>
                <p>
                    @if($status->value === 'Paid')
                        Your payment has been confirmed by IDontPay99.
                    @elseif($status->value === 'Failed')
                        The card was declined. No charge was made.
                    @else
                        This payment is {{ strtolower($status->value) }} and can no longer be paid here.
                    @endif
                </p>

                @if($status->value === 'Failed')
                    <div class="payment-checkout-actions">
                        @if($payment->election_id)
                            <a class="payment-checkout-pay-button" href="{{ route('payments.checkout.election', $payment->election_id) }}">Try again</a>
                            <a class="payment-checkout-pay-button payment-checkout-pay-button--secondary" href="{{ route('elections.show', $payment->election_id) }}">Back to election</a>
                        @else
                            <a class="payment-checkout-pay-button" href="{{ route('payments.checkout.show', $payment) }}">Try again</a>
                        @endif
                    </div>
                @elseif($status->value === 'Paid')
                    <div class="payment-checkout-actions">
                        <a class="payment-checkout-pay-button payment-checkout-pay-button--secondary" href="{{ route('payments.checkout.receipt', $payment) }}">View receipt</a>
                    </div>
                @endif
            </div>

            @if($status->value === 'Paid' && $payment->purpose === \App\Models\Payment::PURPOSE_ELECTION_HOSTING)
                <div class="payment-checkout-reference">
                    <span>Payment reference</span>
                    <strong>{{ $payment->provider_reference }}</strong>
                    <p>Copy this reference into your election's <em>Submit payment for verification</em> form. Your payment will be verified automatically.</p>
                    @if($payment->election_id)
                        <a class="payment-checkout-pay-button payment-checkout-pay-button--secondary" href="{{ route('elections.show', $payment->election_id) }}">Back to my election</a>
                    @endif
                </div>
            @endif
        @else
            @if (session('success'))
                <p class="payment-checkout-note">{{ session('success') }}</p>
            @endif

            <form method="POST" action="{{ route('payments.checkout.charge', $payment) }}" class="payment-checkout-form" autocomplete="off">
                @csrf

                <label>
                    <span>Card number</span>
                    <input type="text" name="card_number" inputmode="numeric" placeholder="4242 4242 4242 4242" maxlength="23" required autocomplete="off">
                </label>

                <label>
                    <span>Cardholder name</span>
                    <input type="text" name="card_holder" placeholder="A. Candidate" maxlength="100" required autocomplete="off">
                </label>

                <div class="payment-checkout-row">
                    <label>
                        <span>Expiry (MM/YY)</span>
                        <input type="text" name="expiry" placeholder="12/29" maxlength="5" pattern="(0[1-9]|1[0-2])/[0-9]{2}" required autocomplete="off">
                    </label>
                    <label>
                        <span>CVV</span>
                        <input type="text" name="cvv" inputmode="numeric" placeholder="123" maxlength="4" required autocomplete="off">
                    </label>
                </div>

                @if ($errors->any())
                    <p class="payment-checkout-error">Check the card details and try again.</p>
                @endif

                <label class="payment-checkout-simulate">
                    <input type="checkbox" name="simulate_failure" value="1">
                    <span>Simulate a failed payment (test mode)</span>
                </label>

                <button type="submit" class="payment-checkout-pay-button">Pay {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</button>

                <p class="payment-checkout-fineprint">
                    This is a fake gateway for local testing only — nothing entered here is stored or sent anywhere.
                    Any card number works and settles as Paid; a number ending <strong>0002</strong>, an expired MM/YY, or checking the box above, always declines.
                </p>
            </form>
        @endif
    </div>
</div>
@endsection
