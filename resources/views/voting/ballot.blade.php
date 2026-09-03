{{-- PIC: Goh Chun Wen — Voting Process module. --}}
@extends('layouts.app')

@section('title', 'Secure ballot')

@section('content')
<section class="voting-page voting-page--narrow">
    <a class="back-link" href="{{ route('elections.index') }}">← Back to elections</a>
    <header class="page-heading">
        <p class="eyebrow">Secure ballot &middot; Election #{{ $ballot['id'] }}</p>
        <h1>{{ $ballot['title'] }}</h1>
        <p>{{ $ballot['description'] }}</p>
        @if ($ballot['eligibilityRules'])
            <p class="callout"><strong>Eligibility:</strong> {{ $ballot['eligibilityRules'] }}</p>
        @endif
    </header>

    @if (session('duplicate_vote'))
        <article class="card confirmation-card">
            <span class="status status--pending">Not accepted</span>
            <h2>Vote not submitted</h2>
            <p>A vote from your account was already recorded for this election.</p>
            <p><strong>This submission was not accepted, and your existing vote remains unchanged.</strong></p>
            <p>VoteTrust never displays your selected candidate after submission.</p>
        </article>
    @elseif ($hasVoted)
        <article class="card confirmation-card">
            <span class="confirmation-mark" aria-hidden="true">✓</span>
            <h2>Your vote has already been recorded</h2>
            <p>VoteTrust never displays your selected candidate after submission.</p>
        </article>
    @elseif (empty($ballot['candidacies']))
        <article class="card empty-state">
            <h2>No approved candidates</h2>
            <p>This election cannot accept a vote until at least one candidacy is approved.</p>
        </article>
    @else
        <form class="card ballot-form" method="POST" action="{{ route('voting.cast', $ballot['id']) }}">
            @csrf
            <input type="hidden" name="request_id" value="{{ $requestId }}">
            <input type="hidden" name="timestamp" value="{{ $timestamp }}">
            <fieldset>
                <legend>Select one candidate</legend>
                <p class="muted">Your selection is final after submission.</p>
                <div class="candidate-options">
                    @foreach ($ballot['candidacies'] as $candidate)
                        @php
                            $candidateName = $candidate['name'];
                            $initials = collect(preg_split('/\s+/', trim($candidateName)) ?: [])
                                ->filter()
                                ->take(2)
                                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                ->implode('');
                            $candidateInputId = 'candidate-'.$candidate['candidateId'];
                        @endphp
                        <article class="candidate-option">
                            <label class="candidate-option__choice" for="{{ $candidateInputId }}">
                                <input
                                    id="{{ $candidateInputId }}"
                                    type="radio"
                                    name="candidate_id"
                                    value="{{ $candidate['candidateId'] }}"
                                    required
                                    @checked(old('candidate_id') == $candidate['candidateId'])
                                >
                                @if ($candidate['photoUrl'])
                                    <img class="candidate-option__avatar" src="{{ $candidate['photoUrl'] }}" alt="Photo of {{ $candidateName }}" loading="lazy">
                                @else
                                    <span class="candidate-option__avatar candidate-option__avatar--initials" aria-hidden="true">{{ $initials ?: 'C' }}</span>
                                @endif
                                <span class="candidate-option__identity">
                                    <strong>{{ $candidateName }}</strong>
                                    @if ($candidate['position'])<small>{{ $candidate['position'] }}</small>@endif
                                </span>
                                <span class="candidate-option__select-label">Select</span>
                            </label>

                            @if ($candidate['introduction'])
                                <p class="candidate-option__introduction">{{ $candidate['introduction'] }}</p>
                            @endif

                            <details class="candidate-option__profile">
                                <summary>Review application details</summary>
                                <div class="candidate-option__profile-grid">
                                    <section class="candidate-option__profile-item candidate-option__profile-item--wide">
                                        <h3>Manifesto</h3>
                                        <p>{{ $candidate['manifesto'] ?: 'No manifesto was provided.' }}</p>
                                    </section>
                                    @if ($candidate['qualifications'])
                                        <section class="candidate-option__profile-item">
                                            <h3>Experience</h3>
                                            <p>{{ $candidate['qualifications'] }}</p>
                                        </section>
                                    @endif
                                    @if ($candidate['reasonForCandidacy'])
                                        <section class="candidate-option__profile-item">
                                            <h3>Why they are running</h3>
                                            <p>{{ $candidate['reasonForCandidacy'] }}</p>
                                        </section>
                                    @endif
                                </div>
                            </details>
                        </article>
                    @endforeach
                </div>
            </fieldset>
            <button class="button button--primary button--wide" type="submit">Submit final vote</button>
        </form>
    @endif
</section>
@endsection
