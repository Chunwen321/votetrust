@php($candidacy = $election->candidacies->first())
@php($isPublic = \App\ValueObjects\EligibilityRules::fromStored($election->eligibility_rules)->mode() === \App\ValueObjects\EligibilityRules::PUBLIC)
@php($milestone = $candidateMilestones[(int) $election->getKey()])
@php($displayStatus = $candidateElectionStatuses[(int) $election->getKey()])
<article class="card election-list-item" role="listitem">
    <div class="election-list-item__main">
        <div class="election-list-item__heading">
            <h3>{{ $election->title }}</h3>
            <span class="badge election-state election-state-{{ $displayStatus->modifier }}">{{ $displayStatus->label }}</span>
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
