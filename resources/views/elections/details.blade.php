@extends('layouts.app')

@section('title', $election->title)

@section('content')
<div class="election-shell election-details-page">
    <nav class="election-details-back-nav" aria-label="Election navigation">
        <a class="election-link election-details-back-link" href="{{ route('elections.index') }}">&larr; All elections</a>
    </nav>

    <header class="election-page-header election-details-hero">
        <div class="election-details-hero-copy">
            <div class="election-card-meta election-details-meta">
                <span class="election-status election-status-{{ $displayStatus->modifier }}">
                    {{ $displayStatus->label }}
                </span>
                <span class="election-details-visibility">{{ $eligibility->mode() === \App\ValueObjects\EligibilityRules::PUBLIC ? 'Public election' : 'Private election' }}</span>
            </div>
            <h1>{{ $election->title }}</h1>
            <p class="election-details-position"><strong>Position</strong><span>{{ $election->position_sought ?: 'Not recorded' }}</span></p>
            <p class="election-details-description">{{ $election->description }}</p>
        </div>

        @if ($canApply)
            <a class="election-button election-button-primary" href="{{ route('candidate.applications.create', $election) }}">Apply as candidate</a>
        @endif
    </header>

    @if ($candidacy)
        <section class="card role-empty-state election-details-opportunity">
            <div class="role-empty-state__icon" aria-hidden="true">✓</div>
            <div>
                <h2>Your candidate application</h2>
                <p>Your application status for this election is <strong>{{ $candidacy->status }}</strong>.</p>
            </div>
            <a class="button button-quiet button-small" href="{{ route('candidate.applications.show', $candidacy) }}">View application</a>
        </section>
    @elseif ($eligibility->mode() === \App\ValueObjects\EligibilityRules::PUBLIC)
        <section class="card role-empty-state election-details-opportunity">
            <div class="role-empty-state__icon" aria-hidden="true">+</div>
            <div>
                <h2>Public candidate opportunity</h2>
                @if (! auth()->user()->isVerified())
                    <p>Verify your profile with eKYC before applying as a candidate.</p>
                @else
                    <p>{{ $canApply ? 'Nominations are open. Review this election, then apply for its fixed candidate position.' : 'Candidate applications are not currently open.' }}</p>
                @endif
            </div>
            @if (! auth()->user()->isVerified())
                <a class="button button-quiet button-small" href="{{ route('profile.verify') }}">Verify profile</a>
            @elseif ($canApply)
                <a class="button button-quiet button-small" href="{{ route('candidate.applications.create', $election) }}">Apply as candidate</a>
            @endif
        </section>
    @endif

    <section class="election-detail-grid">
        <article class="election-card election-details-card">
            <h2>Election details</h2>
            <dl class="election-facts election-facts-stacked election-details-facts">
                <div><dt>Eligibility</dt><dd>{{ $eligibility->summary() }}</dd></div>
                <div class="election-details-period election-details-period-nomination">
                    <dt>Nomination period</dt>
                    <dd>
                        <span>
                            <small>Starts</small>
                            @if ($election->nomination_start)
                                <time datetime="{{ $election->nomination_start->toIso8601String() }}"><strong>{{ $election->nomination_start->format('d M Y') }}</strong><em>{{ $election->nomination_start->format('H:i') }}</em></time>
                            @else
                                <strong>Not scheduled</strong>
                            @endif
                        </span>
                        <span>
                            <small>Ends</small>
                            @if ($election->nomination_end)
                                <time datetime="{{ $election->nomination_end->toIso8601String() }}"><strong>{{ $election->nomination_end->format('d M Y') }}</strong><em>{{ $election->nomination_end->format('H:i') }}</em></time>
                            @else
                                <strong>Not scheduled</strong>
                            @endif
                        </span>
                    </dd>
                </div>
                <div class="election-details-period election-details-period-voting">
                    <dt>Voting period</dt>
                    <dd>
                        <span>
                            <small>Starts</small>
                            @if ($election->voting_start)
                                <time datetime="{{ $election->voting_start->toIso8601String() }}"><strong>{{ $election->voting_start->format('d M Y') }}</strong><em>{{ $election->voting_start->format('H:i') }}</em></time>
                            @else
                                <strong>Not scheduled</strong>
                            @endif
                        </span>
                        <span>
                            <small>Ends</small>
                            @if ($election->voting_end)
                                <time datetime="{{ $election->voting_end->toIso8601String() }}"><strong>{{ $election->voting_end->format('d M Y') }}</strong><em>{{ $election->voting_end->format('H:i') }}</em></time>
                            @else
                                <strong>Not scheduled</strong>
                            @endif
                        </span>
                    </dd>
                </div>
            </dl>
        </article>

        <aside class="election-card election-details-card election-details-access">
            <h2>Your access</h2>
            @if ($candidacy)
                <p>This private or public election is visible because you have a candidate assignment.</p>
            @else
                <p>This election is publicly visible to signed-in VoteTrust users.</p>
            @endif
        </aside>
    </section>
</div>
@endsection
