{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Admin dashboard')

@section('content')
<div class="user-module">
<section class="dashboard-hero admin-hero">
    <div>
        <p class="eyebrow">System administration</p>
        <h1>Election participant control</h1>
        <p>Review election activity and manage voter and candidate access.</p>
    </div>
</section>

<section class="metrics" aria-label="System summary">
    <article class="metric-card"><span>Total elections</span><strong>{{ $counts['elections'] }}</strong><small>All election records</small></article>
    <article class="metric-card status-approved"><span>Active elections</span><strong>{{ $counts['active'] }}</strong><small>Approved, nomination, or voting</small></article>
    <article class="metric-card"><span>Approved voters</span><strong>{{ $counts['voters'] }}</strong><small>Across all elections</small></article>
    <article class="metric-card status-pending"><span>Pending candidates</span><strong>{{ $counts['pending_candidates'] }}</strong><small>Require an admin decision</small></article>
</section>

<section class="card table-card">
    <div class="section-title"><div><p class="eyebrow">Election oversight</p><h2>Recent elections</h2></div></div>
    @if($recentElections->isEmpty())
        <div class="empty-state"><h3>No elections created</h3><p>Election creation is managed by the election module.</p></div>
    @else
        <div class="table-scroll">
            <table>
                <thead><tr><th>Election</th><th>Lifecycle</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                @foreach($recentElections as $election)
                    <tr>
                        <td><strong>{{ $election->title }}</strong><br><small>#{{ $election->getKey() }}</small></td>
                        <td><span class="badge status-{{ strtolower($election->status) }}">{{ str_replace('_', ' ', $election->status) }}</span></td>
                        <td>{{ optional($election->updated_at)->format('d M Y, g:i A') }}</td>
                        <td><a class="button button-small" href="{{ route('admin.participants.show', $election) }}">Manage participants</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
</div>
@endsection
