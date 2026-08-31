{{-- PIC: Goh Chun Wen - Public aggregate election results. --}}
@extends('layouts.app')

@section('title', 'Results - '.$election['title'])

@section('content')
<div class="voting-results-page">
    <nav class="voting-results-back" aria-label="Results navigation">
        @auth
            <a href="{{ route(auth()->user()->isAdmin() ? 'admin.elections.index' : 'elections.index') }}">&larr; Back to elections</a>
        @else
            <a href="{{ route('home') }}">&larr; Back to VoteTrust</a>
        @endauth
    </nav>

    <header class="voting-results-hero">
        <div class="voting-results-hero__copy">
            <div class="voting-results-hero__meta">
                <span>Final results</span>
                <span>Election #{{ $election['id'] }}</span>
            </div>
            <h1>{{ $election['title'] }}</h1>
            <p>{{ $election['description'] ?: 'Voting has closed and the final aggregate count is now available.' }}</p>
        </div>

        <dl class="voting-results-summary" aria-label="Election result summary">
            <div>
                <dt>Total votes</dt>
                <dd>{{ number_format($totalVotes) }}</dd>
            </div>
            <div>
                <dt>Candidates</dt>
                <dd>{{ count($results) }}</dd>
            </div>
            <div class="voting-results-summary__position">
                <dt>Position</dt>
                <dd>{{ $election['position'] ?: 'Not recorded' }}</dd>
            </div>
        </dl>
    </header>

    @if ($hasVoted)
        <p class="voting-results-participation" role="status">
            <span aria-hidden="true">&#10003;</span>
            Your vote is included in the aggregate total. Your individual choice remains private.
        </p>
    @endif

    <section class="voting-results-panel" aria-labelledby="official-tally-heading">
        <div class="voting-results-panel__heading">
            <div>
                <p class="eyebrow">Count complete</p>
                <h2 id="official-tally-heading">Official tally</h2>
                <p>Results are ranked by votes received. Only aggregate totals are published.</p>
            </div>
            <span class="voting-results-closed-badge">Voting closed</span>
        </div>

        @if ($results === [])
            <div class="voting-results-empty">
                <strong>No candidates were available in the final ballot.</strong>
                <p>The election is closed, but there is no candidate tally to display.</p>
            </div>
        @else
            @if ($totalVotes === 0)
                <div class="voting-results-empty voting-results-empty--inline">
                    <strong>No votes were recorded.</strong>
                    <p>All candidates finished with zero votes, so no winner is declared.</p>
                </div>
            @endif

            <ol class="voting-results-list">
                @foreach ($results as $result)
                    <li @class(['voting-result-row', 'is-winner' => $result['isWinner']])>
                        <span class="voting-result-row__rank" aria-label="Rank {{ $loop->iteration }}">{{ $loop->iteration }}</span>
                        <div class="voting-result-row__body">
                            <div class="voting-result-row__topline">
                                <h3>{{ $result['candidateName'] }}</h3>
                                @if ($result['isWinner'])
                                    <span class="voting-result-row__winner">{{ $winnerCount > 1 ? 'Joint winner' : 'Winner' }}</span>
                                @endif
                            </div>
                            <div
                                class="voting-result-row__bar"
                                role="progressbar"
                                aria-label="{{ $result['candidateName'] }} received {{ $result['percentage'] }} percent of votes"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="{{ $result['percentage'] }}"
                            >
                                <span style="width: {{ min(100, $result['percentage']) }}%"></span>
                            </div>
                        </div>
                        <div class="voting-result-row__total">
                            <strong>{{ number_format($result['votes']) }}</strong>
                            <span>{{ Illuminate\Support\Str::plural('vote', $result['votes']) }}</span>
                            <small>{{ number_format($result['percentage'], 1) }}%</small>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <aside class="voting-results-integrity" aria-label="Result privacy information">
        <span aria-hidden="true">i</span>
        <div>
            <strong>Ballot privacy is preserved</strong>
            <p>VoteTrust publishes candidate totals only. It does not reveal how any individual voter voted.</p>
        </div>
    </aside>
</div>
@endsection
