{{-- PIC: Low Wen Kai - Election Management Module --}}

@extends('layouts.app')

@section('title', 'Election administration')

@section('content')
<div class="election-shell">
    @php
        $actionableStatuses = [\App\States\ElectionStatus::PENDING_FEE_QUOTE->value, \App\States\ElectionStatus::PENDING_ADMIN_APPROVAL->value];
    @endphp
    <header class="election-page-header">
        <div><p class="election-eyebrow">Administrator workspace</p><h1>Election administration</h1><p>Set organizer hosting fees, then verify submitted payments before nominations open.</p></div>
        <span class="election-status">{{ $adminActionCount }} awaiting admin action</span>
    </header>

    <section class="election-grid">
        @forelse ($elections as $election)
            @php($requiresAdmin = in_array($election->status, $actionableStatuses, true))
            <article class="election-card {{ $requiresAdmin ? 'election-approval-priority' : '' }}">
                <div class="election-card-meta"><span class="election-status">{{ str($election->status)->replace('_', ' ')->lower()->headline() }}</span><span>#{{ $election->getKey() }}</span></div>
                <h2>{{ $election->title }}</h2>
                <p>{{ $election->description }}</p>
                <dl class="election-facts"><div><dt>Organiser</dt><dd>{{ $election->organizer?->display_name ?? $election->organizer?->name ?? $election->organizer?->username ?? 'Unknown' }}</dd></div><div><dt>Hosting fee</dt><dd>{{ $election->hosting_fee === null ? 'Quote required' : $election->currency.' '.number_format((float) $election->hosting_fee, 2) }}</dd></div><div><dt>Payment</dt><dd>@if ($election->payment_verified_at) Verified @elseif ($election->payment_reference) Submitted @else Not submitted @endif</dd></div></dl>
                <a class="election-link" href="{{ route('admin.elections.show', $election) }}">Review election &rarr;</a>
            </article>
        @empty
            <article class="election-card election-empty"><h2>No elections found</h2></article>
        @endforelse
    </section>
    {{ $elections->links() }}
</div>
@endsection
