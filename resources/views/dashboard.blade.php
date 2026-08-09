{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="user-module">
    <section class="dashboard-hero">
        <div>
            <p class="eyebrow">Account dashboard</p>
            <h1>Welcome back, {{ $user->full_name ?: $user->username }}.</h1>
            <p>Use one VoteTrust account to vote in some elections and organise others.</p>
        </div>
        <div class="account-summary">
            <span>Account status</span>
            <strong>{{ str($user->account_status ?? 'ACTIVE')->lower()->headline() }}</strong>
        </div>
    </section>

    <section class="dashboard-role-grid" aria-label="Election overview">
        <a class="dashboard-role-card" href="{{ route('elections.index') }}#voting-elections">
            <span class="dashboard-role-card__label">Voting elections</span>
            <strong>{{ $overview['votingElections'] }}</strong>
            <small>Election{{ $overview['votingElections'] === 1 ? '' : 's' }} where you have voter access</small>
        </a>
        <a class="dashboard-role-card" href="{{ route('elections.index') }}#voting-elections">
            <span class="dashboard-role-card__label">Ready to vote</span>
            <strong>{{ $overview['readyToVote'] }}</strong>
            <small>Open ballots awaiting your choice</small>
        </a>
        <a class="dashboard-role-card" href="{{ route('elections.index') }}#organised-elections">
            <span class="dashboard-role-card__label">Organised elections</span>
            <strong>{{ $overview['organizedElections'] }}</strong>
            <small>Election{{ $overview['organizedElections'] === 1 ? '' : 's' }} you manage</small>
        </a>
        <a class="dashboard-role-card dashboard-role-card--create" href="{{ route('elections.create') }}">
            <span class="dashboard-role-card__label">Organise an election</span>
            <strong aria-hidden="true">+</strong>
            <small>Start a new election from this account</small>
        </a>
    </section>

    <section class="dashboard-collections" aria-label="Recent election activity">
        <article class="card dashboard-collection">
            <div class="section-title">
                <div>
                    <p class="eyebrow">Voter</p>
                    <h2>Recent voting elections</h2>
                </div>
                <a class="back-link" href="{{ route('elections.index') }}#voting-elections">View all</a>
            </div>
            @if ($enrollments->isEmpty())
                <div class="empty-state compact-empty"><p>You do not have voter access for an election yet.</p></div>
            @else
                <div class="activity-list">
                    @foreach ($enrollments->take(3) as $enrollment)
                        @php($votingStatus = $votingStatuses[(int) $enrollment->election_id] ?? null)
                        <div class="activity-item">
                            <div>
                                <strong>{{ $enrollment->election_title }}</strong>
                                <span>{{ str($enrollment->election_status)->replace('_', ' ')->lower()->headline() }}</span>
                            </div>
                            <div class="activity-statuses">
                                <span class="badge status-{{ strtolower($enrollment->status) }}">{{ str($enrollment->status)->lower()->headline() }}</span>
                                @if (($votingStatus['available'] ?? false) && ($votingStatus['hasVoted'] ?? false))
                                    <span class="badge status-voted">Voted</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="card dashboard-collection">
            <div class="section-title">
                <div>
                    <p class="eyebrow">Organiser</p>
                    <h2>Recent organised elections</h2>
                </div>
                <a class="back-link" href="{{ route('elections.index') }}#organised-elections">View all</a>
            </div>
            @if ($organizedElections->isEmpty())
                <div class="empty-state compact-empty">
                    <p>Have an election to run?</p>
                    <a class="button button-small" href="{{ route('elections.create') }}">Create election</a>
                </div>
            @else
                <div class="activity-list">
                    @foreach ($organizedElections->take(3) as $election)
                        <div class="activity-item">
                            <div>
                                <strong>{{ $election->title }}</strong>
                                <span>{{ str($election->status)->replace('_', ' ')->lower()->headline() }}</span>
                            </div>
                            <a class="button button-quiet button-small" href="{{ route('elections.show', $election) }}">Manage</a>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>
</div>
@endsection
