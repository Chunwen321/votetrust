{{-- PIC: Risky Puah Kaho - Payment Module --}}
@extends('layouts.app')

@section('title', 'Payment API health')

@section('content')
<div class="user-module">
<section class="dashboard-hero admin-hero">
    <div>
        <p class="eyebrow">Payment module</p>
        <h1>Payment API health</h1>
        <p>Live diagnostics for the Payment module's own API: reachability, key validation, and the active gateway.</p>
    </div>
</section>

<section class="metrics" aria-label="Payment API status">
    <article class="metric-card @if($reachable) status-approved @else status-rejected @endif">
        <span>API reachable</span>
        <strong>{{ $reachable ? 'Yes' : 'No' }}</strong>
        <small>{{ $endpoint }}</small>
    </article>
    <article class="metric-card @if($keyValid) status-approved @else status-rejected @endif">
        <span>API key valid</span>
        <strong>{{ $keyValid ? 'Yes' : 'No' }}</strong>
        <small>X-API-Key checked against PAYMENT_API_KEY</small>
    </article>
    <article class="metric-card status-approved">
        <span>Mock gateway</span>
        <strong>{{ $gateway }}</strong>
        <small>Bound via PaymentGatewayContract</small>
    </article>
</section>

@if($error)
<section class="card table-card">
    <div class="section-title"><div><p class="eyebrow">Diagnostic</p><h2>Last error</h2></div></div>
    <p>{{ $error }}</p>
</section>
@endif

<section class="card table-card">
    <div class="section-title"><div><p class="eyebrow">Live test</p><h2>Run a test payment</h2></div></div>
    <p>Fires a throwaway MYR 1.00 transaction through the real mock gateway (the same code path Election and Candidate use) and shows the settled result below.</p>

    @if(session('payment_test_result'))
        @php
            $testResult = session('payment_test_result');
            $testStatusClass = match ($testResult['status']) {
                'Paid' => 'approved',
                'Failed' => 'rejected',
                default => 'unavailable',
            };
        @endphp
        <div class="metric-card status-{{ $testStatusClass }}" style="margin-bottom: 16px;">
            <span>Test payment #{{ $testResult['paymentId'] }} result</span>
            <strong>{{ $testResult['status'] }}</strong>
            <small>Reference {{ $testResult['reference'] }} &middot; settled {{ $testResult['ranAt'] }}</small>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.payments.health.test') }}" class="form-stack" style="max-width: 420px;">
        @csrf
        <label class="check-field">
            <input type="checkbox" name="simulate_failure" value="1">
            <span>Simulate a failing payment (reference ends in "-FAIL")</span>
        </label>
        <button type="submit" class="button">Run test payment</button>
    </form>
</section>

<section class="card table-card">
    <div class="section-title"><div><p class="eyebrow">Recent activity</p><h2>Last {{ $recentPayments->count() }} payment transactions</h2></div></div>
    @if($recentPayments->isEmpty())
        <div class="empty-state"><h3>No payments yet</h3><p>Transactions will appear here once the verify or status endpoints are exercised.</p></div>
    @else
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Reference</th>
                        <th>Organizer</th>
                        <th>Candidate</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recentPayments as $payment)
                    @php
                        $statusClass = match ($payment->status) {
                            'Paid' => 'approved',
                            'Pending' => 'pending',
                            'Failed' => 'rejected',
                            'Cancelled' => 'cancelled',
                            'Refunded' => 'suspended',
                            default => 'unavailable',
                        };
                    @endphp
                    <tr>
                        <td>#{{ $payment->id }}</td>
                        <td>{{ $payment->purpose ?? '—' }}</td>
                        <td><span class="badge status-{{ $statusClass }}">{{ $payment->status }}</span></td>
                        <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                        <td>{{ $payment->provider_reference ?? '—' }}</td>
                        <td>{{ $payment->organizer?->name ?? '—' }}</td>
                        <td>{{ $payment->candidate?->name ?? '—' }}</td>
                        <td>{{ $payment->updated_at?->format('d M Y, g:i A') ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
</div>
@endsection
