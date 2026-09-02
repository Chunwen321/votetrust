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
                    <p>Approved public elections appear automatically. Private elections require an organiser assignment.</p>
                </div>
            </div>
            <span class="section-count">{{ $voterEnrollments->count() }} election{{ $voterEnrollments->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($voterEnrollments->isEmpty())
            <article class="card role-empty-state">
                <div class="role-empty-state__icon" aria-hidden="true">✓</div>
                <div>
                    <h3>No voting elections yet</h3>
                    <p>Approved public elections will appear here automatically, including elections organised before you registered. Private elections appear after an organiser assigns you.</p>
                </div>
            </article>
        @else
            <div class="election-list" role="list">
                @foreach ($voterEnrollments as $enrollment)
                    @php($votingStatus = $votingStatuses[(int) $enrollment->election_id] ?? null)
                    @php($isApprovedVoter = $enrollment->status === \App\Models\VoterEnrollment::APPROVED)
                    @php($isVotingOpen = $enrollment->election_status === \App\Models\Election::VOTING_OPEN)
                    @php($isClosed = $enrollment->election_status === \App\Models\Election::CLOSED)
                    @php($isPublicAccess = $enrollment->election_access_type === 'PUBLIC')
                    @php($isVotingAllowed = $enrollment->election_voting_allowed)
                    @php($hasRecordedVote = ($votingStatus['available'] ?? false) && ($votingStatus['hasVoted'] ?? false))
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
                            <p>Voter access: <span class="badge status-{{ strtolower($enrollment->status) }}">{{ $isPublicAccess ? 'Public' : str($enrollment->status)->lower()->headline() }}</span></p>
                            @include('elections.partials.card-milestone', ['milestone' => $milestone])
                        </div>
                        <div class="election-list-item__action">
                            @if ($isClosed)
                                <a class="button button-quiet button-small" href="{{ route('voting.results', $enrollment->election_id) }}" aria-label="View results for {{ $enrollment->election_title }}">View results <span aria-hidden="true">&rarr;</span></a>
                            @elseif (in_array($enrollment->status, [\App\Models\VoterEnrollment::REJECTED, \App\Models\VoterEnrollment::SUSPENDED], true))
                                <span class="badge status-{{ strtolower($enrollment->status) }}">Voting access {{ str($enrollment->status)->lower() }}</span>
                            @elseif (! $user->isVerified())
                                <a class="button button-quiet button-small" href="{{ route('profile.verify') }}">Verify to vote</a>
                            @elseif (! $isApprovedVoter)
                                <span class="item-note">Voting access pending</span>
                            @elseif ($isVotingOpen && $hasRecordedVote)
                                <span class="badge status-voted">Vote recorded</span>
                                <a class="button button-quiet button-small" href="{{ route('elections.show', $enrollment->election_id) }}" aria-label="View election {{ $enrollment->election_title }}">View election <span aria-hidden="true">&rarr;</span></a>
                            @elseif ($isVotingOpen && $isVotingAllowed === false)
                                <span class="item-note">{{ $milestone->tone === 'upcoming' ? 'Voting has not opened' : 'Voting has ended' }}</span>
                            @elseif ($isVotingOpen && $isApprovedVoter)
                                <a class="button button-small" href="{{ route('voting.ballot', $enrollment->election_id) }}" aria-label="Open the ballot for {{ $enrollment->election_title }}">Open ballot <span aria-hidden="true">&rarr;</span></a>
                            @else
                                <a class="button button-quiet button-small" href="{{ route('elections.show', $enrollment->election_id) }}" aria-label="View election {{ $enrollment->election_title }}">View election <span aria-hidden="true">&rarr;</span></a>
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
                    <p>Apply to current public opportunities and track elections where you already have an application or assignment.</p>
                </div>
            </div>
            <span class="section-count">{{ $candidateElections->count() }} election{{ $candidateElections->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($candidateElections->isEmpty())
            <article class="card role-empty-state">
                <div class="role-empty-state__icon" aria-hidden="true">i</div>
                <div>
                    <h3>No candidate opportunities or applications</h3>
                    <p>Open public nominations and your existing candidate records will appear here.</p>
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
                                <p>{{ $isPublic ? 'Your application status:' : 'Private candidate assignment:' }} <span class="badge status-{{ strtolower($candidacy->status) }}">{{ str($candidacy->status)->lower()->headline() }}</span></p>
                            @elseif ($isPublic)
                                @if (! $user->isVerified())
                                    <p>Public election &mdash; verify your profile before applying.</p>
                                @elseif ($candidateApplicationsOpen[(int) $election->getKey()])
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
                            @if ($election->status === \App\Models\Election::CLOSED)
                                <a class="button button-quiet button-small" href="{{ route('voting.results', $election) }}" aria-label="View results for {{ $election->title }}">View results <span aria-hidden="true">&rarr;</span></a>
                            @else
                                <a class="button button-quiet button-small" href="{{ route('elections.show', $election) }}" aria-label="View {{ $election->title }}">View election <span aria-hidden="true">&rarr;</span></a>
                            @endif
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
                            @if ($election->status === \App\Models\Election::CLOSED)
                                <a class="button button-quiet button-small" href="{{ route('voting.results', $election) }}" aria-label="View results for {{ $election->title }}">View results <span aria-hidden="true">&rarr;</span></a>
                            @elseif ($election->status === \App\Models\Election::DRAFT)
                                <a class="button button-quiet button-small" href="{{ route('elections.edit', $election) }}" aria-label="Continue setting up {{ $election->title }}">Continue setup <span aria-hidden="true">&rarr;</span></a>
                            @else
                                <a class="button button-quiet button-small" href="{{ route('elections.show', $election) }}" aria-label="Manage {{ $election->title }}">Manage election <span aria-hidden="true">&rarr;</span></a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
