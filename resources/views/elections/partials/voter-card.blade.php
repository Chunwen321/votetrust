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
