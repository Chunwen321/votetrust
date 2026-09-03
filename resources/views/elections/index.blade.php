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
        <div class="election-hub__actions">
            @if ($user->isVerified())
                <a class="button" href="{{ route('elections.create') }}">Create an election</a>
            @else
                <a class="button" href="{{ route('profile.verify') }}">Verify profile to participate</a>
            @endif
        </div>
    </section>

    <p class="election-hub__timezone" role="note">All election dates and times are shown in {{ $electionTimezoneLabel }}.</p>

    <section id="voting-elections" class="election-role-section election-role-section--voter" aria-labelledby="voting-elections-title">
        <div class="section-title">
            <div class="election-role-heading">
                <span class="election-role-heading__icon" aria-hidden="true">V</span>
                <div>
                    <p class="eyebrow">Your voter area</p>
                    <h2 id="voting-elections-title">Voting elections</h2>
                    <p>Current elections appear here. Recently closed elections remain available below for seven days.</p>
                </div>
            </div>
            <span class="section-count">{{ $activeVoterEnrollments->count() }} election{{ $activeVoterEnrollments->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($activeVoterEnrollments->isEmpty())
            <article class="card role-empty-state">
                <div class="role-empty-state__icon" aria-hidden="true">✓</div>
                <div>
                    <h3>No ongoing voting elections</h3>
                    <p>Approved public elections appear automatically. Private elections appear after an organiser assigns you.</p>
                </div>
            </article>
        @else
            <div class="election-list" role="list">
                @foreach ($activeVoterEnrollments as $enrollment)
                    @include('elections.partials.voter-card', ['enrollment' => $enrollment])
                @endforeach
            </div>
        @endif

        @if ($recentClosedVoterEnrollments->isNotEmpty())
            <details class="recently-closed">
                <summary>
                    <span>Recently closed</span>
                    <small>{{ $recentClosedVoterEnrollments->count() }} election{{ $recentClosedVoterEnrollments->count() === 1 ? '' : 's' }} &middot; available here for 7 days</small>
                </summary>
                <div class="election-list" role="list">
                    @foreach ($recentClosedVoterEnrollments as $enrollment)
                        @include('elections.partials.voter-card', ['enrollment' => $enrollment])
                    @endforeach
                </div>
            </details>
        @endif
    </section>

    <section id="candidate-elections" class="election-role-section election-role-section--candidate" aria-labelledby="candidate-elections-title">
        <div class="section-title">
            <div class="election-role-heading">
                <span class="election-role-heading__icon" aria-hidden="true">C</span>
                <div>
                    <p class="eyebrow">Your candidate area</p>
                    <h2 id="candidate-elections-title">Election opportunities</h2>
                    <p>Apply to current public opportunities and track elections where you already have an application or assignment.</p>
                </div>
            </div>
            <span class="section-count">{{ $activeCandidateElections->count() }} election{{ $activeCandidateElections->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($activeCandidateElections->isEmpty())
            <article class="card role-empty-state">
                <div class="role-empty-state__icon" aria-hidden="true">i</div>
                <div>
                    <h3>No candidate opportunities or applications</h3>
                    <p>Open public nominations and your existing candidate records will appear here.</p>
                </div>
            </article>
        @else
            <div class="election-list" role="list">
                @foreach ($activeCandidateElections as $election)
                    @include('elections.partials.candidate-card', ['election' => $election])
                @endforeach
            </div>
        @endif

        @if ($recentClosedCandidateElections->isNotEmpty())
            <details class="recently-closed">
                <summary>
                    <span>Recently closed</span>
                    <small>{{ $recentClosedCandidateElections->count() }} election{{ $recentClosedCandidateElections->count() === 1 ? '' : 's' }} &middot; available here for 7 days</small>
                </summary>
                <div class="election-list" role="list">
                    @foreach ($recentClosedCandidateElections as $election)
                        @include('elections.partials.candidate-card', ['election' => $election])
                    @endforeach
                </div>
            </details>
        @endif
    </section>

    <section id="organised-elections" class="election-role-section election-role-section--organiser" aria-labelledby="organised-elections-title">
        <div class="section-title">
            <div class="election-role-heading">
                <span class="election-role-heading__icon" aria-hidden="true">O</span>
                <div>
                    <p class="eyebrow">Your organiser area</p>
                    <h2 id="organised-elections-title">Organised elections</h2>
                    <p>Create and manage elections without changing your account type.</p>
                </div>
            </div>
            @if ($user->isVerified())
                <a class="button button-quiet button-small" href="{{ route('elections.create') }}">Organise an election</a>
            @else
                <a class="button button-quiet button-small" href="{{ route('profile.verify') }}">Verify to organise</a>
            @endif
        </div>

        @if ($activeOrganizedElections->isEmpty())
            <article class="card role-empty-state role-empty-state--action">
                <div class="role-empty-state__icon" aria-hidden="true">{{ $organizedElections->isEmpty() ? '+' : '✓' }}</div>
                <div>
                    @if ($organizedElections->isEmpty())
                        <h3>Ready to organise?</h3>
                        <p>Set up an election in a few steps. You can still vote in other elections with this same account.</p>
                    @else
                        <h3>No ongoing organised elections</h3>
                        <p>Your recently closed elections are available below, and older elections are in history.</p>
                    @endif
                </div>
                @if ($user->isVerified())
                    <a class="button" href="{{ route('elections.create') }}">Create election</a>
                @else
                    <a class="button" href="{{ route('profile.verify') }}">Verify profile</a>
                @endif
            </article>
        @else
            <div class="election-list" role="list">
                @foreach ($activeOrganizedElections as $election)
                    @include('elections.partials.organizer-card', ['election' => $election])
                @endforeach
            </div>
        @endif

        @if ($recentClosedOrganizedElections->isNotEmpty())
            <details class="recently-closed">
                <summary>
                    <span>Recently closed</span>
                    <small>{{ $recentClosedOrganizedElections->count() }} election{{ $recentClosedOrganizedElections->count() === 1 ? '' : 's' }} &middot; available here for 7 days</small>
                </summary>
                <div class="election-list" role="list">
                    @foreach ($recentClosedOrganizedElections as $election)
                        @include('elections.partials.organizer-card', ['election' => $election])
                    @endforeach
                </div>
            </details>
        @endif
    </section>
</div>
@endsection
