@php($milestone = $organizerMilestones[(int) $election->getKey()])
@php($displayStatus = $organizerElectionStatuses[(int) $election->getKey()])
<article class="card election-list-item" role="listitem">
    <div class="election-list-item__main">
        <div class="election-list-item__heading">
            <h3>{{ $election->title }}</h3>
            <span class="badge election-state election-state-{{ $displayStatus->modifier }}">{{ $displayStatus->label }}</span>
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
