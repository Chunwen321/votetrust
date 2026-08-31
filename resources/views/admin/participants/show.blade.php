{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Election participants')

@section('content')
<div class="user-module">
<a class="back-link" href="{{ route('admin.dashboard') }}">&larr; Admin dashboard</a>
<section class="page-heading split-heading">
    <div>
        <p class="eyebrow">Election #{{ $election['id'] }}</p>
        <h1>{{ $election['title'] }}</h1>
        <p>View the voter and candidate records submitted by the election organiser.</p>
    </div>
    <span class="badge status-{{ strtolower($election['status']) }}">{{ str_replace('_', ' ', $election['status']) }}</span>
</section>

<section class="card role-empty-state" aria-label="Administrator permission notice">
    <div class="role-empty-state__icon" aria-hidden="true">i</div>
    <div>
        <h2>Participant records are read-only</h2>
        <p>Candidate decisions and voter access are managed by the election organiser. Administrators can review these records when deciding whether to approve or reject the election.</p>
    </div>
    <a class="button button-quiet button-small" href="{{ route('admin.elections.show', $election['id']) }}">Review election</a>
</section>

<section class="metrics">
    <article class="metric-card"><span>Voter records</span><strong>{{ $counts['voters'] }}</strong><small>{{ $counts['approved_voters'] }} approved</small></article>
    <article class="metric-card"><span>Candidate records</span><strong>{{ $counts['candidates'] }}</strong><small>{{ $counts['approved_candidates'] }} approved</small></article>
</section>

<section id="voters" class="card management-section">
    <div class="section-title">
        <div><p class="eyebrow">Voter access</p><h2>Voter records</h2></div>
        <p>{{ $voters->count() }} records</p>
    </div>

    @if($voters->isEmpty())
        <p class="empty-state compact-empty">No voters have been added to this election.</p>
    @else
        <div class="table-scroll">
            <table>
                <thead><tr><th>User</th><th>Account</th><th>Access status</th></tr></thead>
                <tbody>
                @foreach($voters as $voter)
                    <tr>
                        <td><strong>{{ $voter->full_name ?: $voter->email }}</strong><br><small>{{ $voter->email }}</small></td>
                        <td>{{ $voter->account_status }}</td>
                        <td><span class="badge status-{{ strtolower($voter->status) }}">{{ $voter->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section id="candidates" class="card management-section">
    <div class="section-title">
        <div><p class="eyebrow">Ballot overview</p><h2>Candidate records</h2></div>
        <p>{{ $candidates->count() }} records</p>
    </div>

    @if($candidates->isEmpty())
        <p class="empty-state compact-empty">No candidates have been added to this election.</p>
    @else
        <div class="table-scroll">
            <table>
                <thead><tr><th>Candidate</th><th>Account</th><th>Decision</th></tr></thead>
                <tbody>
                @foreach($candidates as $candidate)
                    <tr>
                        <td><strong>{{ $candidate->full_name ?: $candidate->email }}</strong><br><small>{{ $candidate->email }}</small></td>
                        <td>{{ $candidate->account_status }}</td>
                        <td><span class="badge status-{{ strtolower($candidate->status) }}">{{ $candidate->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
</div>
@endsection
