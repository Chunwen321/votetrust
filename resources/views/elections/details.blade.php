@extends('layouts.app')

@section('title', $election->title)

@section('content')
<div class="election-shell">
    <a class="election-link" href="{{ route('elections.index') }}">&larr; All elections</a>

    <header class="election-page-header">
        <div>
            <div class="election-card-meta">
                <span class="election-status election-status-{{ strtolower(str_replace('_', '-', $election->status)) }}">
                    {{ str($election->status)->replace('_', ' ')->lower()->headline() }}
                </span>
                <span>{{ $eligibility->mode() === \App\ValueObjects\EligibilityRules::PUBLIC ? 'Public election' : 'Private election' }}</span>
            </div>
            <h1>{{ $election->title }}</h1>
            <p><strong>Position:</strong> {{ $election->position_sought ?: 'Not recorded' }}</p>
            <p>{{ $election->description }}</p>
        </div>

        @if ($canApply)
            <a class="election-button election-button-primary" href="{{ route('candidate.applications.create', ['election_id' => $election->getKey()]) }}">Become a candidate</a>
        @endif
    </header>

    @if ($candidacy)
        <section class="card role-empty-state">
            <div class="role-empty-state__icon" aria-hidden="true">✓</div>
            <div>
                <h2>You are assigned as a candidate</h2>
                <p>Your candidate status for this election is <strong>{{ $candidacy->status }}</strong>.</p>
            </div>
            <a class="button button-quiet button-small" href="{{ route('candidate.profile.show') }}">View candidate profile</a>
        </section>
    @elseif ($eligibility->mode() === \App\ValueObjects\EligibilityRules::PUBLIC)
        <section class="card role-empty-state">
            <div class="role-empty-state__icon" aria-hidden="true">+</div>
            <div>
                <h2>Public candidate opportunity</h2>
                @if (! auth()->user()->isVerified())
                    <p>Verify your profile with eKYC before applying as a candidate.</p>
                @else
                    <p>{{ $canApply ? 'Nominations are open. You may apply to become a candidate.' : 'Candidate applications are not currently open.' }}</p>
                @endif
            </div>
            @if (! auth()->user()->isVerified())
                <a class="button button-quiet button-small" href="{{ route('profile.verify') }}">Verify profile</a>
            @endif
        </section>
    @endif

    <section class="election-detail-grid">
        <article class="election-card">
            <h2>Election details</h2>
            <dl class="election-facts election-facts-stacked">
                <div><dt>Eligibility</dt><dd>{{ $eligibility->summary() }}</dd></div>
                <div><dt>Nominations</dt><dd>{{ $election->nomination_start?->format('d M Y, H:i') ?? 'Not scheduled' }} &ndash; {{ $election->nomination_end?->format('d M Y, H:i') ?? 'Not scheduled' }}</dd></div>
                <div><dt>Voting</dt><dd>{{ $election->voting_start?->format('d M Y, H:i') ?? 'Not scheduled' }} &ndash; {{ $election->voting_end?->format('d M Y, H:i') ?? 'Not scheduled' }}</dd></div>
            </dl>
        </article>

        <aside class="election-card">
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
