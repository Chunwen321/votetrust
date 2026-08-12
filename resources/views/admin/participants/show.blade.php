{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Election participants')

@section('content')
<div class="user-module">
<a class="back-link" href="{{ route('admin.dashboard') }}">&larr; Admin dashboard</a>
<section class="page-heading split-heading">
    <div><p class="eyebrow">Election #{{ $election['id'] }}</p><h1>{{ $election['title'] }}</h1><p>Manage voter eligibility and candidate approval for this election.</p></div>
    <span class="badge status-{{ strtolower($election['status']) }}">{{ str_replace('_', ' ', $election['status']) }}</span>
</section>

<section class="metrics">
    <article class="metric-card"><span>Voter records</span><strong>{{ $counts['voters'] }}</strong><small>{{ $counts['approved_voters'] }} approved</small></article>
    <article class="metric-card"><span>Candidate records</span><strong>{{ $counts['candidates'] }}</strong><small>{{ $counts['approved_candidates'] }} approved</small></article>
</section>

<section id="voters" class="card management-section">
    <div class="section-title"><div><p class="eyebrow">Voter access</p><h2>Add and manage voters</h2></div><div class="section-actions"><p>{{ $voters->count() }} records</p><a class="back-link" href="{{ route('admin.elections.show', $election['id']) }}">Review election &rarr;</a></div></div>
    <form method="post" action="{{ route('admin.participants.voters.store', $election['id']) }}" class="management-add-form">
        @csrf
        <label>User<select name="user_id" required><option value="">Choose a registered user</option>@foreach($availableVoters as $person)<option value="{{ $person->getKey() }}">{{ $person->display_name }} ({{ $person->email }})</option>@endforeach</select></label>
        <label>Status<select name="status">@foreach(\App\Services\AdminManagementService::VOTER_STATUSES as $status)<option value="{{ $status }}">{{ ucfirst(strtolower($status)) }}</option>@endforeach</select></label>
        <button type="submit">Save voter</button>
    </form>
    @error('user_id')<p class="field-error">{{ $message }}</p>@enderror
    @error('status')<p class="field-error">{{ $message }}</p>@enderror

    @if($voters->isEmpty())
        <p class="empty-state compact-empty">No voters have been added to this election.</p>
    @else
        <div class="table-scroll"><table><thead><tr><th>User</th><th>Account</th><th>Status</th><th>Control</th></tr></thead><tbody>
        @foreach($voters as $voter)
            <tr>
                <td><strong>{{ $voter->full_name ?: $voter->email }}</strong><br><small>{{ $voter->email }}</small></td>
                <td>{{ $voter->account_status }}</td>
                <td><span class="badge status-{{ strtolower($voter->status) }}">{{ $voter->status }}</span></td>
                <td><form method="post" action="{{ route('admin.participants.voters.update', [$election['id'], $voter->user_id]) }}" class="row-action-form">@csrf @method('PATCH')<select name="status">@foreach(\App\Services\AdminManagementService::VOTER_STATUSES as $status)<option value="{{ $status }}" @selected($voter->status === $status)>{{ ucfirst(strtolower($status)) }}</option>@endforeach</select><button class="button-small" type="submit">Save</button></form></td>
            </tr>
        @endforeach
        </tbody></table></div>
    @endif
</section>

<section id="candidates" class="card management-section">
    <div class="section-title"><div><p class="eyebrow">Ballot management</p><h2>Add and manage candidates</h2></div><p>{{ $candidates->count() }} records</p></div>
    <form method="post" action="{{ route('admin.participants.candidates.store', $election['id']) }}" class="management-add-form">
        @csrf
        <label>User<select name="user_id" required><option value="">Choose a registered user</option>@foreach($availableCandidates as $person)<option value="{{ $person->getKey() }}">{{ $person->display_name }} ({{ $person->email }})</option>@endforeach</select></label>
        <label>Status<select name="status">@foreach(\App\Services\AdminManagementService::CANDIDATE_STATUSES as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></label>
        <button type="submit">Save candidate</button>
    </form>

    @if($candidates->isEmpty())
        <p class="empty-state compact-empty">No candidates have been added to this election.</p>
    @else
        <div class="table-scroll"><table><thead><tr><th>Candidate</th><th>Account</th><th>Status</th><th>Control</th></tr></thead><tbody>
        @foreach($candidates as $candidate)
            <tr>
                <td><strong>{{ $candidate->full_name ?: $candidate->email }}</strong><br><small>{{ $candidate->email }}</small></td>
                <td>{{ $candidate->account_status }}</td>
                <td><span class="badge status-{{ strtolower($candidate->status) }}">{{ $candidate->status }}</span></td>
                <td><form method="post" action="{{ route('admin.participants.candidates.update', [$election['id'], $candidate->user_id]) }}" class="row-action-form">@csrf @method('PATCH')<select name="status">@foreach(\App\Services\AdminManagementService::CANDIDATE_STATUSES as $status)<option value="{{ $status }}" @selected($candidate->status === $status)>{{ $status }}</option>@endforeach</select><button class="button-small" type="submit">Save</button></form></td>
            </tr>
        @endforeach
        </tbody></table></div>
    @endif
</section>
</div>
@endsection
