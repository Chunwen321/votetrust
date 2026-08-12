{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Member account management')

@section('content')
<div class="user-module">
    <a class="back-link" href="{{ route('admin.dashboard') }}">&larr; Admin dashboard</a>

    <section class="page-heading split-heading">
        <div>
            <p class="eyebrow">Account access</p>
            <h1>Member account management</h1>
            <p>Search registered members and restore or disable access. Administrator accounts are protected from changes here.</p>
        </div>
    </section>

    <section class="metrics" aria-label="Member account summary">
        <article class="metric-card"><span>All members</span><strong>{{ $accountCounts['active'] + $accountCounts['disabled'] }}</strong><small>Non-administrator accounts</small></article>
        <article class="metric-card status-approved"><span>Active</span><strong>{{ $accountCounts['active'] }}</strong><small>Can sign in and participate</small></article>
        <article class="metric-card status-rejected"><span>Disabled</span><strong>{{ $accountCounts['disabled'] }}</strong><small>Can be restored safely</small></article>
    </section>

    <section class="card table-card">
        <div class="section-title"><div><p class="eyebrow">Directory</p><h2>Registered members</h2></div><p>{{ $users->total() }} matching accounts</p></div>

        <form method="get" action="{{ route('admin.users.index') }}" class="management-filter-form">
            <label>Search<input type="search" name="search" value="{{ request('search') }}" placeholder="Name or email"></label>
            <label>Access status<select name="status"><option value="">All statuses</option>@foreach($accountStatuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(strtolower($status)) }}</option>@endforeach</select></label>
            <button type="submit">Filter accounts</button>
            @if(request()->filled('search') || request()->filled('status'))
                <a class="button button-quiet" href="{{ route('admin.users.index') }}">Clear</a>
            @endif
        </form>

        @if($users->isEmpty())
            <p class="empty-state compact-empty">No member accounts match the selected filters.</p>
        @else
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Member</th><th>Contact</th><th>Access</th><th>Last updated</th><th>Control</th></tr></thead>
                    <tbody>
                    @foreach($users as $member)
                        <tr>
                            <td><strong>{{ $member->display_name }}</strong></td>
                            <td>{{ $member->email }}</td>
                            <td><span class="badge status-{{ strtolower($member->account_status) }}">{{ $member->account_status }}</span></td>
                            <td>{{ optional($member->updated_at)->format('d M Y, g:i A') }}</td>
                            <td>
                                <form method="post" action="{{ route('admin.users.update', $member) }}" class="row-action-form">
                                    @csrf
                                    @method('PATCH')
                                    @if($member->account_status === 'ACTIVE')
                                        <input type="hidden" name="status" value="DISABLED">
                                        <button class="button-small button-danger" type="submit">Disable</button>
                                    @else
                                        <input type="hidden" name="status" value="ACTIVE">
                                        <button class="button-small" type="submit">Restore access</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $users->links() }}</div>
        @endif
    </section>
</div>
@endsection
