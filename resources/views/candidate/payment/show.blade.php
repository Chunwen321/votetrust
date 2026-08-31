{{-- PIC: Wong Shun Bin — Candidate Registration payment-status view. --}}
@extends('candidate.layout')

@section('title', 'Candidate payment')

@section('candidate-content')
    <div class="candidate-page-heading">
        <p class="candidate-eyebrow">Security deposit</p>
        <h1>Candidate payment</h1>
    </div>

    @if (session('success'))
        <p class="candidate-payment-notice" role="status">{{ session('success') }}</p>
    @endif

    <section class="candidate-card">
        <h2>{{ $candidacy->election?->title ?? 'Election' }}</h2>
        <dl class="candidate-definition-list">
            <div>
                <dt>Payment ID</dt>
                <dd>{{ $payment }}</dd>
            </div>
            <div>
                <dt>Amount</dt>
                <dd>MYR {{ number_format((float) config('candidate.deposit_amount', 10), 2) }}</dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd>{{ $paymentStatus->displayStatus() }}</dd>
            </div>
        </dl>

        @if (! $paymentStatus->available)
            <p>Payment status could not be verified. Payment-dependent actions remain disabled.</p>
        @elseif ($paymentStatus->isPending())
            <p>Your deposit is awaiting payment in the Payment Module.</p>
            <a class="candidate-button" href="{{ route('payments.checkout.show', $payment) }}">Pay now</a>
        @elseif ($paymentStatus->paid)
            <p>Your payment is complete. Application approval will follow the configured payment workflow.</p>
        @else
            <p>This payment is no longer payable.</p>
        @endif

        <p class="candidate-help">{{ $paymentStatus->message }}</p>

        <a class="candidate-button" href="{{ route('candidate.applications.show', $candidacy) }}">Back to application</a>
    </section>
@endsection
