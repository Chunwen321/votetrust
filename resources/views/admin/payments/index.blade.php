{{-- PIC: Low Wen Kai, Risky Puah Kaho - Election payment administration --}}
@extends('layouts.app')

@section('title', 'Payment review')

@section('content')
@php($hasFilters = filled($filters['search']) || filled($filters['verification']))
<div class="user-module">
    <section class="dashboard-hero admin-hero">
        <div>
            <p class="eyebrow">Payment management</p>
            <h1>Review hosting payments</h1>
            <p>Verify submitted election-hosting payments before giving final election approval.</p>
        </div>
        <a class="button button-quiet" href="{{ route('admin.payments.health') }}">Payment API health</a>
    </section>

    <section class="metrics" aria-label="Payment review summary">
        <article class="metric-card status-pending"><span>Awaiting verification</span><strong>{{ $awaitingCount }}</strong><small>Require a payment check</small></article>
        <article class="metric-card status-approved"><span>Verified</span><strong>{{ $verifiedCount }}</strong><small>Ready for election approval</small></article>
        <article class="metric-card"><span>Total in queue</span><strong>{{ $awaitingCount + $verifiedCount }}</strong><small>Submitted hosting payments</small></article>
    </section>

    <section class="card table-card">
        <div class="section-title">
            <div><p class="eyebrow">Verification queue</p><h2>Election payments</h2></div>
            <p>{{ $payments->total() }} matching {{ \Illuminate\Support\Str::plural('payment', $payments->total()) }}</p>
        </div>

        <form method="get" action="{{ route('admin.payments.index') }}" class="management-filter-form">
            <label>Search<input type="search" name="search" maxlength="100" value="{{ $filters['search'] }}" placeholder="Election, organiser, or verified reference"></label>
            <label>Verification<select name="verification"><option value="">All submitted</option><option value="awaiting" @selected($filters['verification'] === 'awaiting')>Awaiting verification</option><option value="verified" @selected($filters['verification'] === 'verified')>Verified</option></select></label>
            <button type="submit">Filter payments</button>
            @if ($hasFilters)<a class="button button-quiet" href="{{ route('admin.payments.index') }}">Clear</a>@endif
        </form>

        @if ($payments->isEmpty())
            <div class="empty-state compact-empty"><h3>No hosting payments found</h3><p>Submitted election payments will appear here for administrator verification.</p></div>
        @else
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Election</th><th>Organiser</th><th>Amount</th><th>Reference</th><th>Verification</th><th>Action</th></tr></thead>
                    <tbody>
                    @foreach ($payments as $payment)
                        <tr>
                            <td><strong>{{ $payment->title }}</strong><br><small>Election #{{ $payment->getKey() }}</small></td>
                            <td>{{ $payment->organizer?->display_name ?? $payment->organizer?->email ?? 'Unknown' }}</td>
                            <td>{{ $payment->currency }} {{ number_format((float) $payment->hosting_fee, 2) }}</td>
                            <td>
                                @if ($payment->payment_reference)
                                    <code>{{ $payment->payment_reference }}</code>
                                @else
                                    <small>Matched by election ID</small>
                                @endif
                            </td>
                            <td>
                                @if ($payment->payment_verified_at)
                                    <span class="badge status-approved">Verified</span><br><small>{{ $payment->payment_verified_at->format('d M Y, g:i A') }}</small>
                                @else
                                    <span class="badge status-pending">Awaiting verification</span>
                                @endif
                            </td>
                            <td>
                                @if ($payment->payment_verified_at)
                                    <a class="button button-small" href="{{ route('admin.elections.show', $payment) }}">Continue approval</a>
                                @else
                                    <form class="payment-review-form" method="post" action="{{ route('admin.payments.verify', $payment) }}">
                                        @csrf
                                        <input type="hidden" name="version" value="{{ $payment->version }}">
                                        <label><span>Current password</span><input name="current_password" type="password" autocomplete="current-password" required></label>
                                        <button class="button-small" type="submit">Verify payment</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $payments->links() }}</div>
        @endif
    </section>
</div>
@endsection
