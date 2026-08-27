{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Admin dashboard')

@section('content')
<div class="user-module">
<section class="dashboard-hero admin-hero">
    <div>
        <p class="eyebrow">System administration</p>
        <h1>Election participant control</h1>
        <p>Review election activity, manage member access, and control voter and candidate participation.</p>
    </div>
</section>

<section class="metrics" aria-label="System summary">
    <article class="metric-card"><span>Total elections</span><strong>{{ $counts['elections'] }}</strong><small>All election records</small></article>
    <article class="metric-card status-approved"><span>Active elections</span><strong>{{ $counts['active'] }}</strong><small>Approved, nomination, or voting</small></article>
    <article class="metric-card"><span>Approved voters</span><strong>{{ $counts['voters'] }}</strong><small>Across all elections</small></article>
    <article class="metric-card status-pending"><span>Pending candidates</span><strong>{{ $counts['pending_candidates'] }}</strong><small>Require an admin decision</small></article>
</section>

<section class="admin-action-grid" aria-label="Administrator tools">
    <a class="card admin-action-card" href="{{ route('admin.elections.index') }}">
        <span class="eyebrow">Election workflow</span>
        <h2>Review election requests</h2>
        <p>{{ $counts['pending_election_approvals'] }} election {{ \Illuminate\Support\Str::plural('request', $counts['pending_election_approvals']) }} awaiting review.</p>
        <span class="back-link">Open election workspace &rarr;</span>
    </a>
    <a class="card admin-action-card" href="{{ route('admin.payments.index') }}">
        <span class="eyebrow">Payment verification</span>
        <h2>Review hosting payments</h2>
        <p>Check submitted references and verify payment before an election can receive final approval.</p>
        <span class="back-link">Open payment queue &rarr;</span>
    </a>
    <a class="card admin-action-card" href="{{ route('admin.users.index') }}">
        <span class="eyebrow">Account access</span>
        <h2>Manage member accounts</h2>
        <p>{{ $counts['members'] }} member accounts, including {{ $counts['disabled_members'] }} currently disabled.</p>
        <span class="back-link">Open member directory &rarr;</span>
    </a>
</section>

@if($pendingElectionApprovals->isNotEmpty())
<section class="card table-card">
    <div class="section-title"><div><p class="eyebrow">Action required</p><h2>Election requests awaiting review</h2></div><a class="back-link" href="{{ route('admin.elections.index') }}">View all &rarr;</a></div>
    <div class="activity-list">
        @foreach($pendingElectionApprovals as $election)
            <div class="activity-item">
                <div><strong>{{ $election['title'] }}</strong><span>Organiser: {{ $election['organizerName'] }}</span></div>
                <a class="button button-small" href="{{ route('admin.elections.show', $election['id']) }}">Review</a>
            </div>
        @endforeach
    </div>
</section>
@endif

<section class="card table-card">
    <div class="section-title"><div><p class="eyebrow">Election oversight</p><h2>Recent elections</h2></div></div>
    @if($recentElections->isEmpty())
        <div class="empty-state"><h3>No elections created</h3><p>Election creation is managed by the election module.</p></div>
    @else
        <div class="table-scroll">
            <table>
                <thead><tr><th>Election</th><th>Lifecycle</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                @foreach($recentElections as $election)
                    <tr>
                        <td><strong>{{ $election['title'] }}</strong><br><small>#{{ $election['id'] }}</small></td>
                        <td><span class="badge status-{{ strtolower($election['status']) }}">{{ str_replace('_', ' ', $election['status']) }}</span></td>
                        <td>{{ $election['updatedAt'] ? \Carbon\CarbonImmutable::parse($election['updatedAt'])->format('d M Y, g:i A') : 'Unavailable' }}</td>
                        <td><div class="admin-table-actions"><a class="button button-small" href="{{ route('admin.participants.show', $election['id']) }}">Participants</a><a class="button button-small button-quiet" href="{{ route('admin.elections.show', $election['id']) }}">Review</a></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
</div>
@endsection
