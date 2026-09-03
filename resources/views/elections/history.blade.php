@extends('layouts.app')

@section('title', 'Election history')

@section('content')
<div class="user-module election-hub election-history">
    <section class="page-heading split-heading">
        <div>
            <p class="eyebrow">Past elections</p>
            <h1>Election history</h1>
            <p>Closed elections move here seven days after voting ends. You can still review their published results.</p>
        </div>
        <div class="election-hub__actions">
            <a class="button button-quiet" href="{{ route('elections.index') }}">&larr; Back to my elections</a>
        </div>
    </section>

    <p class="election-hub__timezone" role="note">All election dates and times are shown in {{ $electionTimezoneLabel }}.</p>

    @if ($historicalVoterEnrollments->isEmpty() && $historicalCandidateElections->isEmpty() && $historicalOrganizedElections->isEmpty())
        <article class="card role-empty-state election-history__empty">
            <div class="role-empty-state__icon" aria-hidden="true">H</div>
            <div>
                <h2>No election history yet</h2>
                <p>Elections will appear here once they have been closed for more than seven days.</p>
            </div>
        </article>
    @else
        @if ($historicalVoterEnrollments->isNotEmpty())
            <section class="election-role-section election-role-section--voter" aria-labelledby="voter-history-title">
                <div class="section-title">
                    <div class="election-role-heading">
                        <span class="election-role-heading__icon" aria-hidden="true">V</span>
                        <div><p class="eyebrow">Your voter area</p><h2 id="voter-history-title">Voting history</h2></div>
                    </div>
                    <span class="section-count">{{ $historicalVoterEnrollments->count() }}</span>
                </div>
                <div class="election-list" role="list">
                    @foreach ($historicalVoterEnrollments as $enrollment)
                        @include('elections.partials.voter-card', ['enrollment' => $enrollment])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($historicalCandidateElections->isNotEmpty())
            <section class="election-role-section election-role-section--candidate" aria-labelledby="candidate-history-title">
                <div class="section-title">
                    <div class="election-role-heading">
                        <span class="election-role-heading__icon" aria-hidden="true">C</span>
                        <div><p class="eyebrow">Your candidate area</p><h2 id="candidate-history-title">Candidate history</h2></div>
                    </div>
                    <span class="section-count">{{ $historicalCandidateElections->count() }}</span>
                </div>
                <div class="election-list" role="list">
                    @foreach ($historicalCandidateElections as $election)
                        @include('elections.partials.candidate-card', ['election' => $election])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($historicalOrganizedElections->isNotEmpty())
            <section class="election-role-section election-role-section--organiser" aria-labelledby="organizer-history-title">
                <div class="section-title">
                    <div class="election-role-heading">
                        <span class="election-role-heading__icon" aria-hidden="true">O</span>
                        <div><p class="eyebrow">Your organiser area</p><h2 id="organizer-history-title">Organised history</h2></div>
                    </div>
                    <span class="section-count">{{ $historicalOrganizedElections->count() }}</span>
                </div>
                <div class="election-list" role="list">
                    @foreach ($historicalOrganizedElections as $election)
                        @include('elections.partials.organizer-card', ['election' => $election])
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
