@extends('layouts.app')

@section('title', $election->title)

@section('content')
<div class="election-shell election-details-page">
    <nav class="election-details-back-nav" aria-label="Election navigation">
        <a class="election-link election-details-back-link" href="{{ route('elections.index') }}">&larr; All elections</a>
    </nav>

    <header class="election-details-hero-card">
        <div class="election-details-hero-main">
            <div class="election-card-meta election-details-meta">
                <span class="election-status election-status-{{ $displayStatus->modifier }}">{{ $displayStatus->label }}</span>
                <span class="election-details-visibility">{{ $eligibility->mode() === \App\ValueObjects\EligibilityRules::PUBLIC ? 'Public election' : 'Private election' }}</span>
            </div>
            <p class="election-details-kicker">Election overview</p>
            <h1>{{ $election->title }}</h1>
            <p class="election-details-description">{{ $election->description ?: 'No description was provided for this election.' }}</p>
        </div>

        <dl class="election-details-hero-facts">
            <div><dt>Position</dt><dd>{{ $election->position_sought ?: 'Not recorded' }}</dd></div>
            <div><dt>Eligibility</dt><dd>{{ $eligibility->summary() }}</dd></div>
        </dl>

        @if ($canApply)
            <a class="election-button election-button-primary election-details-hero-action" href="{{ route('candidate.applications.create', $election) }}">Apply as candidate</a>
        @endif
    </header>

    <main class="election-details-flow">
        <section class="election-details-section" aria-labelledby="election-information-heading">
            <div class="election-details-section-heading">
                <span>01</span>
                <div><p>Election details</p><h2 id="election-information-heading">Important dates</h2></div>
            </div>

            <div class="election-details-schedule">
                @foreach ([
                    ['name' => 'Nomination period', 'phase' => 'Phase one', 'letter' => 'N', 'modifier' => 'nomination', 'start' => $election->nomination_start, 'end' => $election->nomination_end],
                    ['name' => 'Voting period', 'phase' => 'Phase two', 'letter' => 'V', 'modifier' => 'voting', 'start' => $election->voting_start, 'end' => $election->voting_end],
                ] as $period)
                    <article class="election-details-phase election-details-phase--{{ $period['modifier'] }}">
                        <div class="election-details-phase-heading">
                            <span aria-hidden="true">{{ $period['letter'] }}</span>
                            <div><p>{{ $period['phase'] }}</p><h3>{{ $period['name'] }}</h3></div>
                        </div>
                        <dl>
                            @foreach (['Starts' => $period['start'], 'Ends' => $period['end']] as $label => $date)
                                <div>
                                    <dt>{{ $label }}</dt>
                                    <dd>
                                        @if ($date)
                                            <time datetime="{{ $date->toIso8601String() }}"><strong>{{ $date->format('d M Y') }}</strong><span>{{ $date->format('H:i') }}</span></time>
                                        @else
                                            <strong>Not scheduled</strong>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="election-details-section" aria-labelledby="candidate-details-heading">
            <div class="election-details-section-heading">
                <span>02</span>
                <div><p>Candidate details</p><h2 id="candidate-details-heading">Meet the candidates</h2></div>
                <span class="election-details-count">{{ $candidateProfiles->count() }} approved</span>
            </div>

            @if ($candidacy)
                <div class="election-details-own-application">
                    <span class="election-details-own-application-icon" aria-hidden="true">&#10003;</span>
                    <div><strong>Your candidate application</strong><p>Your application status for this election is <b>{{ $candidacy->status }}</b>.</p></div>
                    <a class="button button-quiet button-small" href="{{ route('candidate.applications.show', $candidacy) }}">View application</a>
                </div>
            @endif

            @if ($candidateProfiles->isEmpty())
                <div class="election-details-empty"><strong>No approved candidates yet</strong><p>Approved candidate profiles will appear here when they are available.</p></div>
            @else
                <div class="election-details-candidates">
                    @foreach ($candidateProfiles as $candidate)
                        @php
                            $candidateName = $candidate->user?->display_name ?? 'Candidate '.$candidate->user_id;
                            $initials = collect(preg_split('/\s+/', trim($candidateName)) ?: [])->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                        @endphp
                        <article class="election-details-candidate-card">
                            <div class="election-details-candidate-header">
                                @if ($candidate->profile_photo)
                                    <img
                                        class="election-details-candidate-avatar election-details-candidate-photo"
                                        src="{{ route('elections.candidates.photo', [$election, $candidate]) }}"
                                        alt="Photo of {{ $candidateName }}"
                                        loading="lazy"
                                    >
                                @else
                                    <span class="election-details-candidate-avatar" aria-hidden="true">{{ $initials ?: 'C' }}</span>
                                @endif
                                <div><p>Candidate for {{ $election->position_sought ?: 'this election' }}</p><h3>{{ $candidateName }}</h3></div>
                                @if ((int) $candidate->user_id === (int) auth()->id())<span class="election-details-you-badge">You</span>@endif
                            </div>
                            @if ($candidate->candidate_introduction)<p class="election-details-candidate-intro">{{ $candidate->candidate_introduction }}</p>@endif
                            <div class="election-details-manifesto"><span>Manifesto</span><p>{{ $candidate->manifesto ?: 'No manifesto was provided.' }}</p></div>
                            @if ($candidate->qualifications)<div class="election-details-qualifications"><span>Experience</span><p>{{ $candidate->qualifications }}</p></div>@endif
                            @if ($candidate->reason_for_candidacy)<div class="election-details-qualifications"><span>Why they are running</span><p>{{ $candidate->reason_for_candidacy }}</p></div>@endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($election->status === \App\Models\Election::CLOSED)
            <section class="election-details-section election-details-results" aria-labelledby="election-results-heading">
                <div class="election-details-section-heading">
                    <span>03</span>
                    <div><p>Final results</p><h2 id="election-results-heading">Official tally</h2></div>
                    <dl class="election-details-result-summary">
                        <div><dt>Total votes</dt><dd>{{ number_format($totalVotes) }}</dd></div>
                        <div><dt>Candidates</dt><dd>{{ count($results) }}</dd></div>
                    </dl>
                </div>

                @if ($results === [])
                    <div class="election-details-empty"><strong>No candidates were available in the final ballot.</strong><p>The election is closed, but there is no candidate tally to display.</p></div>
                @else
                    @if ($totalVotes === 0)
                        <div class="election-details-empty election-details-empty--compact"><strong>No votes were recorded.</strong><p>No winner is declared because every candidate received zero votes.</p></div>
                    @endif
                    <ol class="voting-results-list">
                        @foreach ($results as $result)
                            <li @class(['voting-result-row', 'is-winner' => $result['isWinner']])>
                                <span class="voting-result-row__rank" aria-label="Rank {{ $loop->iteration }}">{{ $loop->iteration }}</span>
                                <div class="voting-result-row__body">
                                    <div class="voting-result-row__topline"><h3>{{ $result['candidateName'] }}</h3>@if ($result['isWinner'])<span class="voting-result-row__winner">{{ $winnerCount > 1 ? 'Joint winner' : 'Winner' }}</span>@endif</div>
                                    <div class="voting-result-row__bar" role="progressbar" aria-label="{{ $result['candidateName'] }} received {{ $result['percentage'] }} percent of votes" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $result['percentage'] }}"><span style="width: {{ min(100, $result['percentage']) }}%"></span></div>
                                </div>
                                <div class="voting-result-row__total"><strong>{{ number_format($result['votes']) }}</strong><span>{{ Illuminate\Support\Str::plural('vote', $result['votes']) }}</span><small>{{ number_format($result['percentage'], 1) }}%</small></div>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <p class="election-details-privacy"><span aria-hidden="true">i</span> Only aggregate totals are displayed. Individual ballot choices remain private.</p>
            </section>
        @endif
    </main>
</div>
@endsection
