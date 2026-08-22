{{-- PIC: Risky Puah Kaho - Payment Module --}}
@extends('layouts.app')

@section('title', 'Payment receipt')

@section('content')
@php
    $status = $payment->paymentStatus();
    $paidAt = $payment->verified_at ?? $payment->created_at;
@endphp
<div class="payment-checkout-shell">
    <div>
        <div class="payment-receipt-actions no-print">
            <button type="button" class="payment-checkout-pay-button" onclick="window.print()">Print receipt</button>
            @if($payment->election_id)
                <a class="payment-checkout-pay-button payment-checkout-pay-button--secondary" href="{{ route('elections.show', $payment->election_id) }}">Back to election</a>
            @endif
        </div>

        <div class="payment-receipt-doc">
            <div class="payment-receipt-header">
                <span class="payment-receipt-org">VoteTrust</span>
                <h2>OFFICIAL PAYMENT RECEIPT</h2>
            </div>

            <div class="payment-receipt-body">
                <div class="payment-receipt-row">
                    <span class="payment-receipt-label">Receipt Number</span>
                    <span class="payment-receipt-value payment-receipt-mono">{{ $payment->provider_reference ?: '—' }}</span>
                </div>
                <div class="payment-receipt-row">
                    <span class="payment-receipt-label">Date &amp; Time</span>
                    <span class="payment-receipt-value">{{ $paidAt ? \Carbon\CarbonImmutable::parse($paidAt)->format('d M Y, H:i') : '—' }}</span>
                </div>

                <hr class="payment-receipt-divider">

                <div class="payment-receipt-row">
                    <span class="payment-receipt-label">Election</span>
                    <span class="payment-receipt-value">{{ $payment->election?->title ?? '—' }}</span>
                </div>

                <hr class="payment-receipt-divider">

                <div class="payment-receipt-row">
                    <span class="payment-receipt-label">Payment Method</span>
                    <span class="payment-receipt-value">IDontPay99</span>
                </div>
                <div class="payment-receipt-row">
                    <span class="payment-receipt-label">Payment ID</span>
                    <span class="payment-receipt-value payment-receipt-mono">{{ $payment->id }}</span>
                </div>

                <hr class="payment-receipt-divider">

                <div class="payment-receipt-total">
                    <span class="payment-receipt-label">Amount Paid</span>
                    <span class="payment-receipt-value">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</span>
                </div>

                <div class="payment-receipt-stamp">
                    <span class="payment-receipt-stamp-badge payment-receipt-stamp--{{ strtolower($status->value) }}">{{ strtoupper($status->value) }}</span>
                </div>
            </div>

            <div class="payment-receipt-footer">
                This receipt is computer-generated and serves as official proof of payment.<br>
                Please retain this document for your records.
            </div>
        </div>
    </div>
</div>
@endsection
