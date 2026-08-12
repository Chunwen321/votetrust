{{-- PIC: Low Wen Kai - Election Management Module --}}

@extends('layouts.app')

@section('title', 'Election administration')

@section('content')
@php
    $reviewStatuses = [
        \App\States\ElectionStatus::PENDING_FEE_QUOTE->value,
        \App\States\ElectionStatus::PENDING_ADMIN_APPROVAL->value,
    ];
    $pendingElections = collect($elections->items())->whereIn('status', $reviewStatuses);
    $otherElections = collect($elections->items())->whereNotIn('status', $reviewStatuses);
@endphp

<div class="election-shell admin-approvals">
    <header class="admin-approvals__hero">
        <div class="admin-approvals__hero-copy">
            <p class="election-eyebrow">Administrator workspace</p>
            <h1>Election approvals</h1>
            <p>Set hosting fees for new requests, then approve paid elections independently before nominations open.</p>
        </div>
        <div class="admin-approvals__counter" aria-label="Election requests awaiting administrator action">
            <span>Awaiting review</span>
            <strong>{{ $pendingApprovalCount }}</strong>
            <small>{{ $pendingApprovalCount === 1 ? 'election needs action' : 'elections need action' }}</small>
        </div>
    </header>

    <section class="admin-approvals__queue" id="review-queue" aria-labelledby="review-queue-heading">
        <div class="admin-approvals__section-heading">
            <div>
                <p class="election-eyebrow">Priority queue</p>
                <h2 id="review-queue-heading">Ready for admin action</h2>
                <p>Quote a hosting fee for new requests, or give the final approval after the organiser’s payment is verified.</p>
            </div>
            <span class="admin-approvals__section-count">{{ $pendingApprovalCount }} {{ \Illuminate\Support\Str::plural('request', $pendingApprovalCount) }}</span>
        </div>

        <div class="admin-approvals__queue-list">
            @forelse ($pendingElections as $election)
                @php($statusClass = 'election-status-'.str($election->status)->lower()->replace('_', '-'))
                <article class="admin-approval-row">
                    <div class="admin-approval-row__marker" aria-hidden="true">!</div>
                    <div class="admin-approval-row__content">
                        <div class="election-card-meta">
                            <span class="election-status {{ $statusClass }}">{{ str($election->status)->replace('_', ' ')->lower()->headline() }}</span>
                            <span>Election #{{ $election->getKey() }}</span>
                        </div>
                        <h3>{{ $election->title }}</h3>
                        <p class="admin-approval-row__description">{{ $election->description ?: 'No election description was provided.' }}</p>
                        <dl class="admin-approval-row__facts">
                            <div><dt>Organiser</dt><dd>{{ $election->organizer?->display_name ?? $election->organizer?->name ?? $election->organizer?->email ?? 'Unknown' }}</dd></div>
                            <div><dt>Payment</dt><dd>{{ $election->payment_verified_at ? 'Verified' : 'Not verified' }}</dd></div>
                            <div><dt>Last updated</dt><dd>{{ $election->updated_at?->format('d M Y, g:i A') ?? 'Unavailable' }}</dd></div>
                        </dl>
                    </div>
                    <a class="election-button election-button-primary admin-approval-row__action" href="{{ route('admin.elections.show', $election) }}">Review request <span aria-hidden="true">&rarr;</span></a>
                </article>
            @empty
                <div class="admin-approvals__empty-state">
                    <span class="admin-approvals__empty-icon" aria-hidden="true">✓</span>
                    <div><strong>All caught up</strong><p>There are no election requests waiting for administrator action.</p></div>
                </div>
            @endforelse
        </div>
    </section>

    <section class="admin-approvals__records" aria-labelledby="election-records-heading">
        <div class="admin-approvals__section-heading">
            <div>
                <p class="election-eyebrow">Election oversight</p>
                <h2 id="election-records-heading">All election records</h2>
                <p>Review an election’s status, organiser, and payment confirmation at a glance.</p>
            </div>
            <span class="admin-approvals__section-count">{{ $elections->total() }} {{ \Illuminate\Support\Str::plural('election', $elections->total()) }}</span>
        </div>
        <div class="election-grid admin-approvals__archive-grid">
            @forelse ($otherElections as $election)
                @php($statusClass = 'election-status-'.str($election->status)->lower()->replace('_', '-'))
                <article class="election-card admin-approvals__record-card">
                    <div class="election-card-meta">
                        <span class="election-status {{ $statusClass }}">{{ str($election->status)->replace('_', ' ')->lower()->headline() }}</span>
                        <span>#{{ $election->getKey() }}</span>
                    </div>
                    <div class="admin-approvals__record-copy">
                        <h3>{{ $election->title }}</h3>
                        <p>{{ $election->description ?: 'No election description was provided.' }}</p>
                    </div>
                    <dl class="election-facts">
                        <div><dt>Organiser</dt><dd>{{ $election->organizer?->display_name ?? $election->organizer?->name ?? $election->organizer?->email ?? 'Unknown' }}</dd></div>
                        <div><dt>Payment</dt><dd>{{ $election->payment_verified_at ? 'Verified' : 'Not verified' }}</dd></div>
                    </dl>
                    <a class="election-link" href="{{ route('admin.elections.show', $election) }}">Open election <span aria-hidden="true">&rarr;</span></a>
                </article>
            @empty
                @if ($pendingElections->isEmpty())
                    <article class="election-card election-empty"><h3>No elections found</h3><p>Election records will appear here once an organiser creates one.</p></article>
                @endif
            @endforelse
        </div>
    </section>
    {{ $elections->links() }}
</div>
@endsection
