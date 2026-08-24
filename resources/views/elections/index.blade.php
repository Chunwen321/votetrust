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
                <a class="button button-quiet" href="{{ route('voting.index') }}">Open voting portal</a>
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
                    <p>Election access is granted separately for each election.</p>
                </div>
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
                    @php($canVote = $user->isVerified() && $enrollment->status === 'APPROVED' && $enrollment->election_status === \App\Models\Election::VOTING_OPEN && ($votingStatus['available'] ?? false) && ! ($votingStatus['hasVoted'] ?? false))
                    @php($milestone = $voterMilestones[(int) $enrollment->election_id])
                    @php($displayStatus = $voterElectionStatuses[(int) $enrollment->election_id])
                    <article class="card election-list-item" role="listitem">
                        <div class="election-list-item__main">
                            <div class="election-list-item__heading">
                                <h3>{{ $enrollment->election_title }}</h3>
                                <span class="badge election-state election-state-{{ $displayStatus->modifier }}">
                                    {{ $displayStatus->label }}
                                </span>
                            </div>
                            <p>Voter access: <span class="badge status-{{ strtolower($enrollment->status) }}">{{ str($enrollment->status)->lower()->headline() }}</span></p>
                            @include('elections.partials.card-milestone', ['milestone' => $milestone])
                        </div>
                        <div class="election-list-item__action">
                            @if (! $user->isVerified())
                                <a class="button button-quiet button-small" href="{{ route('profile.verify') }}">Verify to vote</a>
                            @elseif ($canVote)
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

    <section id="candidate-elections" class="election-role-section election-role-section--candidate" aria-labelledby="candidate-elections-title">
        <div class="section-title">
            <div class="election-role-heading">
                <span class="election-role-heading__icon" aria-hidden="true">C</span>
                <div>
                    <p class="eyebrow">Your candidate area</p>
                    <h2 id="candidate-elections-title">Election opportunities</h2>
                    <p>View public elections you can stand in and private elections where you have been assigned as a candidate.</p>
                </div>
            </div>
            <span class="section-count">{{ $candidateElections->count() }} election{{ $candidateElections->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($candidateElections->isEmpty())
            <article class="card role-empty-state">
                <div class="role-empty-state__icon" aria-hidden="true">i</div>
                <div>
                    <h3>No candidate elections available</h3>
                    <p>Public nomination opportunities and private candidate assignments will appear here.</p>
                </div>
            </article>
        @else
            <div class="election-list" role="list">
                @foreach ($candidateElections as $election)
                    @php($candidacy = $election->candidacies->first())
                    @php($isPublic = \App\ValueObjects\EligibilityRules::fromStored($election->eligibility_rules)->mode() === \App\ValueObjects\EligibilityRules::PUBLIC)
                    @php($milestone = $candidateMilestones[(int) $election->getKey()])
                    @php($displayStatus = $candidateElectionStatuses[(int) $election->getKey()])
                    <article class="card election-list-item" role="listitem">
                        <div class="election-list-item__main">
                            <div class="election-list-item__heading">
                                <h3>{{ $election->title }}</h3>
                                <span class="badge election-state election-state-{{ $displayStatus->modifier }}">
                                    {{ $displayStatus->label }}
                                </span>
                            </div>
                            @if ($candidacy)
                                <p>You have been assigned as a candidate. Status: <span class="badge status-{{ strtolower($candidacy->status) }}">{{ $candidacy->status }}</span></p>
                            @elseif ($isPublic)
                                @if ($candidateApplicationsOpen[(int) $election->getKey()])
                                    <p>Public election &mdash; candidate applications are open now.</p>
                                @elseif ($election->status === \App\Models\Election::NOMINATION_OPEN && $milestone->tone === 'upcoming')
                                    <p>Public election &mdash; applications open when nominations begin.</p>
                                @else
                                    <p>Public election &mdash; candidate applications are closed.</p>
                                @endif
                            @endif
                            @include('elections.partials.card-milestone', ['milestone' => $milestone])
                        </div>
                        <div class="election-list-item__action">
                            <a class="button button-quiet button-small" href="{{ route('elections.show', $election) }}">View election <span aria-hidden="true">&rarr;</span></a>
                        </div>
                    </article>
                @endforeach
            </div>
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

        @if ($organizedElections->isEmpty())
            <article class="card role-empty-state role-empty-state--action">
                <div class="role-empty-state__icon" aria-hidden="true">+</div>
                <div>
                    <h3>Ready to organise?</h3>
                    <p>Set up an election in a few steps. You can still vote in other elections with this same account.</p>
                </div>
                @if ($user->isVerified())
                    <a class="button" href="{{ route('elections.create') }}">Create election</a>
                @else
                    <a class="button" href="{{ route('profile.verify') }}">Verify profile</a>
                @endif
            </article>
        @else
            <div class="election-list" role="list">
                @foreach ($organizedElections as $election)
                    @php($milestone = $organizerMilestones[(int) $election->getKey()])
                    @php($displayStatus = $organizerElectionStatuses[(int) $election->getKey()])
                    <article class="card election-list-item" role="listitem">
                        <div class="election-list-item__main">
                            <div class="election-list-item__heading">
                                <h3>{{ $election->title }}</h3>
                                <span class="badge election-state election-state-{{ $displayStatus->modifier }}">
                                    {{ $displayStatus->label }}
                                </span>
                            </div>
                            <p>Schedule status</p>
                            @include('elections.partials.card-milestone', ['milestone' => $milestone])
                        </div>
                        <div class="election-list-item__action">
                            @if ($election->status === \App\Models\Election::DRAFT)
                                <a class="button button-quiet button-small" href="{{ route('elections.edit', $election) }}">Continue setup <span aria-hidden="true">→</span></a>
                            @else
                                <a class="button button-quiet button-small" href="{{ route('elections.show', $election) }}">Manage election <span aria-hidden="true">→</span></a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
