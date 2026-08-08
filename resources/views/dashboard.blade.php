{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="user-module">
<section class="dashboard-hero">
    <div>
        <p class="eyebrow">Account dashboard</p>
        <h1>Welcome back, {{ $user->full_name ?: $user->username }}.</h1>
        <p>Manage your account and keep track of your voter enrolments in one place.</p>
    </div>
    <div class="account-summary">
        <span>Account status</span>
        <strong>{{ $user->account_status ?? 'ACTIVE' }}</strong>
    </div>
</section>

<section class="metrics" aria-label="Enrolment summary">
    @foreach(['APPROVED' => 'Approved', 'PENDING' => 'Awaiting review', 'REJECTED' => 'Rejected', 'SUSPENDED' => 'Suspended'] as $status => $label)
        <a class="metric-card status-{{ strtolower($status) }}" href="{{ route('voter.dashboard') }}">
            <span>{{ $label }}</span><strong>{{ $counts[$status] }}</strong><small>Election enrolments</small>
        </a>
    @endforeach
</section>

<section class="card table-card">
    <div class="section-title"><div><p class="eyebrow">My elections</p><h2>Recent enrolments</h2></div><a href="{{ route('voter.dashboard') }}">View all</a></div>
    @if($enrollments->isEmpty())
        <div class="empty-state"><h3>No election enrolments yet</h3><p>An administrator can enrol you after confirming your eligibility.</p></div>
    @else
        <div class="activity-list">
            @foreach($enrollments->take(3) as $enrollment)
                @php($votingStatus = $votingStatuses[(int) $enrollment->election_id] ?? null)
                <div class="activity-item">
                    <div><strong>{{ $enrollment->election_title }}</strong><span>Election #{{ $enrollment->election_id }} &middot; {{ $enrollment->election_status }}</span></div>
                    <div class="activity-statuses">
                        <span class="badge status-{{ strtolower($enrollment->status) }}">{{ $enrollment->status }}</span>
                        @if($votingStatus && $votingStatus['available'])
                            <span class="badge {{ $votingStatus['hasVoted'] ? 'status-voted' : 'status-not-voted' }}">
                                {{ $votingStatus['hasVoted'] ? 'Voted' : 'Not voted' }}
                            </span>
                        @else
                            <span class="badge status-unavailable" title="Voting status is supplied by the Voting web service.">Voting service unavailable</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
</div>
@endsection
