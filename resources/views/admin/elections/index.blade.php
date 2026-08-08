{{-- PIC: Low Wen Kai - Election Management Module --}}

@extends('layouts.app')

@section('title', 'Election approvals')

@section('content')
<div class="election-shell">
    <header class="election-page-header">
        <div><p class="election-eyebrow">Administrator workspace</p><h1>Election approvals</h1><p>Review paid elections independently before nominations open.</p></div>
        <span class="election-status">{{ $elections->where('status', \App\States\ElectionStatus::PENDING_ADMIN_APPROVAL->value)->count() }} awaiting review</span>
    </header>

    <section class="election-grid">
        @forelse ($elections as $election)
            <article class="election-card {{ $election->status === \App\States\ElectionStatus::PENDING_ADMIN_APPROVAL->value ? 'election-approval-priority' : '' }}">
                <div class="election-card-meta"><span class="election-status">{{ str($election->status)->replace('_', ' ')->lower()->headline() }}</span><span>#{{ $election->getKey() }}</span></div>
                <h2>{{ $election->title }}</h2>
                <p>{{ $election->description }}</p>
                <dl class="election-facts"><div><dt>Organiser</dt><dd>{{ $election->organizer?->display_name ?? $election->organizer?->name ?? $election->organizer?->username ?? 'Unknown' }}</dd></div><div><dt>Payment</dt><dd>{{ $election->payment_verified_at ? 'Verified' : 'Not verified' }}</dd></div></dl>
                <a class="election-link" href="{{ route('admin.elections.show', $election) }}">Review election &rarr;</a>
            </article>
        @empty
            <article class="election-card election-empty"><h2>No elections found</h2></article>
        @endforelse
    </section>
    {{ $elections->links() }}
</div>
@endsection
