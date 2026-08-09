{{-- A normal user can participate and organise across different elections. --}}
@extends('layouts.app')

@section('title', 'My elections')

@section('content')
<div class="user-module election-hub">
    <section class="page-heading split-heading">
        <div>
            <p class="eyebrow">One account, every election</p>
            <h1>My elections</h1>
            <p>See the elections where you can vote and the elections you organise, all in one place.</p>
        </div>
        <a class="button" href="{{ route('elections.create') }}">Create an election</a>
    </section>

    <section id="voting-elections" class="election-role-section" aria-labelledby="voting-elections-title">
        <div class="section-title">
            <div>
                <p class="eyebrow">Voter</p>
                <h2 id="voting-elections-title">Voting elections</h2>
                <p>Election access is granted separately for each election.</p>
            </div>
            <span class="section-count">{{ $voterEnrollments->count() }} election{{ $voterEnrollments->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($voterEnrollments->isEmpty())
            <article class="card role-empty-state">
                <div class="role-empty-state__icon" aria-hidden="true">✓</div>
                <div>
                    <h3>No voting elections yet</h3>
                    <p>When an organiser or administrator enrols you, the election will appear here.</p>
                </div>
            </article>
        @else
            <div class="election-list" role="list">
                @foreach ($voterEnrollments as $enrollment)
                    @php($votingStatus = $votingStatuses[(int) $enrollment->election_id] ?? null)
                    @php($canVote = $enrollment->status === 'APPROVED' && $enrollment->election_status === \App\Models\Election::VOTING_OPEN && ($votingStatus['available'] ?? false) && ! ($votingStatus['hasVoted'] ?? false))
                    <article class="card election-list-item" role="listitem">
                        <div class="election-list-item__main">
                            <div class="election-list-item__heading">
                                <h3>{{ $enrollment->election_title }}</h3>
                                <span class="badge election-state election-state-{{ strtolower(str_replace('_', '-', $enrollment->election_status)) }}">
                                    {{ str($enrollment->election_status)->replace('_', ' ')->lower()->headline() }}
                                </span>
                            </div>
                            <p>Voter access: <span class="badge status-{{ strtolower($enrollment->status) }}">{{ str($enrollment->status)->lower()->headline() }}</span></p>
                        </div>
                        <div class="election-list-item__action">
                            @if ($canVote)
                                <a class="button button-small" href="{{ route('voting.ballot', $enrollment->election_id) }}">Open ballot</a>
                            @elseif (($votingStatus['available'] ?? false) && ($votingStatus['hasVoted'] ?? false))
                                <span class="badge status-voted">Vote recorded</span>
                            @elseif ($enrollment->election_status === \App\Models\Election::VOTING_OPEN && $enrollment->status !== 'APPROVED')
                                <span class="item-note">Voting access pending</span>
                            @else
                                <a class="button button-quiet button-small" href="{{ route('voting.index') }}">View voting</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section id="organised-elections" class="election-role-section" aria-labelledby="organised-elections-title">
        <div class="section-title">
            <div>
                <p class="eyebrow">Organiser</p>
                <h2 id="organised-elections-title">Organised elections</h2>
                <p>Create and manage elections without changing your account type.</p>
            </div>
            <a class="button button-quiet button-small" href="{{ route('elections.create') }}">Organise an election</a>
        </div>

        @if ($organizedElections->isEmpty())
            <article class="card role-empty-state role-empty-state--action">
                <div class="role-empty-state__icon" aria-hidden="true">+</div>
                <div>
                    <h3>Ready to organise?</h3>
                    <p>Set up an election in a few steps. You can still vote in other elections with this same account.</p>
                </div>
                <a class="button" href="{{ route('elections.create') }}">Create election</a>
            </article>
        @else
            <div class="election-list" role="list">
                @foreach ($organizedElections as $election)
                    <article class="card election-list-item" role="listitem">
                        <div class="election-list-item__main">
                            <div class="election-list-item__heading">
                                <h3>{{ $election->title }}</h3>
                                <span class="badge election-state election-state-{{ strtolower(str_replace('_', '-', $election->status)) }}">
                                    {{ str($election->status)->replace('_', ' ')->lower()->headline() }}
                                </span>
                            </div>
                            <p>
                                @if ($election->voting_start && $election->voting_end)
                                    Voting {{ $election->voting_start->format('d M Y') }} – {{ $election->voting_end->format('d M Y') }}
                                @else
                                    Configure the election schedule to continue.
                                @endif
                            </p>
                        </div>
                        <div class="election-list-item__action">
                            <a class="button button-quiet button-small" href="{{ route('elections.show', $election) }}">Manage election <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
