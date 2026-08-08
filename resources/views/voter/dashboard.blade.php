{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'My voter enrolments')

@section('content')
<div class="user-module">
<section class="page-heading">
    <p class="eyebrow">Voter access</p>
    <h1>My election enrolments</h1>
    <p>Voting eligibility is granted separately for each election by an administrator.</p>
</section>

<section class="card table-card">
    <div class="section-title"><h2>Your election access</h2><p>{{ $enrollments->count() }} enrolment{{ $enrollments->count() === 1 ? '' : 's' }}</p></div>
    @if($enrollments->isEmpty())
        <div class="empty-state"><p>You have not been enrolled for an election yet.</p></div>
    @else
        <div class="table-scroll">
            <table>
                <thead><tr><th>Election</th><th>Election status</th><th>Eligibility</th><th>Participation</th><th>Latest update</th></tr></thead>
                <tbody>
                @foreach($enrollments as $enrollment)
                    @php($votingStatus = $votingStatuses[(int) $enrollment->election_id] ?? null)
                    <tr>
                        <td><strong>{{ $enrollment->election_title }}</strong><br><small>Election #{{ $enrollment->election_id }}</small></td>
                        <td>{{ str_replace('_', ' ', $enrollment->election_status) }}</td>
                        <td><span class="badge status-{{ strtolower($enrollment->status) }}">{{ $enrollment->status }}</span></td>
                        <td>
                            @if($votingStatus && $votingStatus['available'])
                                <span class="badge {{ $votingStatus['hasVoted'] ? 'status-voted' : 'status-not-voted' }}">
                                    {{ $votingStatus['hasVoted'] ? 'Voted' : 'Not voted' }}
                                </span>
                            @else
                                <span class="badge status-unavailable" title="Voting status is supplied by the Voting web service.">Unavailable</span>
                            @endif
                        </td>
                        <td>{{ optional($enrollment->updated_at)->format('d M Y, g:i A') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
</div>
@endsection
