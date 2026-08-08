{{-- PIC: Low Wen Kai - Election Management Module --}}

@extends('layouts.app')

@section('title', $election->title)

@section('content')
@php
    $backRoute = $isAdminView ? route('admin.elections.index') : route('elections.index');
    $transitionRoute = $isAdminView ? route('admin.elections.transition', $election) : route('elections.transition', $election);
    $eligibility = \App\ValueObjects\EligibilityRules::fromStored($election->eligibility_rules);
@endphp
<div class="election-shell">
    <a class="election-link" href="{{ $backRoute }}">&larr; {{ $isAdminView ? 'Approval workspace' : 'All elections' }}</a>
    <header class="election-page-header">
        <div>
            <div class="election-card-meta"><span class="election-status election-status-{{ strtolower(str_replace('_', '-', $election->status)) }}">{{ $availability['lifecycleLabel'] }}</span><span>#{{ $election->getKey() }}</span></div>
            <h1>{{ $election->title }}</h1>
            <p>{{ $election->description }}</p>
        </div>
        @if (! $isAdminView && $election->status === \App\States\ElectionStatus::DRAFT->value)
            <a class="election-button" href="{{ route('elections.edit', $election) }}">Edit election</a>
        @endif
    </header>

    <section class="election-detail-grid">
        <article class="election-card">
            <h2>Lifecycle controls</h2>
            @forelse ($actions as $action)
                <form class="election-action" method="post" action="{{ $transitionRoute }}">
                    @csrf
                    <input type="hidden" name="action" value="{{ $action['action'] }}">
                    <input type="hidden" name="version" value="{{ $election->version }}">
                    <div><h3>{{ $action['label'] }}</h3><p>{{ $action['description'] }}</p></div>
                    @if ($action['requiresPaymentReference'])
                        <label>Payment reference<input name="payment_reference" maxlength="64" required></label>
                    @endif
                    @if ($action['requiresReason'])
                        <label>Reason<input name="reason" maxlength="250" required></label>
                    @endif
                    <label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label>
                    <button class="election-button {{ $action['tone'] === 'danger' ? 'election-button-danger' : 'election-button-primary' }}" type="submit">{{ $action['label'] }}</button>
                </form>
            @empty
                <p>No lifecycle action is currently available.</p>
            @endforelse
        </article>

        <aside class="election-card">
            <h2>Configuration</h2>
            <dl class="election-facts election-facts-stacked">
                <div><dt>Eligibility</dt><dd>{{ $eligibility->summary() }}</dd></div>
                <div><dt>Nominations</dt><dd>{{ \Carbon\CarbonImmutable::parse($election->nomination_start)->format('d M Y, H:i') }} - {{ \Carbon\CarbonImmutable::parse($election->nomination_end)->format('d M Y, H:i') }}</dd></div>
                <div><dt>Voting</dt><dd>{{ \Carbon\CarbonImmutable::parse($election->voting_start)->format('d M Y, H:i') }} - {{ \Carbon\CarbonImmutable::parse($election->voting_end)->format('d M Y, H:i') }}</dd></div>
                <div><dt>Hosting fee</dt><dd>{{ $election->currency }} {{ number_format((float) $election->hosting_fee, 2) }}</dd></div>
                <div><dt>Payment</dt><dd>{{ $availability['hostingPaymentVerified'] ? 'Verified' : 'Required' }}</dd></div>
                <div><dt>Admin approval</dt><dd>{{ $availability['administratorApproved'] ? 'Approved' : 'Required' }}</dd></div>
                <div><dt>Version</dt><dd>{{ $election->version }}</dd></div>
            </dl>
        </aside>
    </section>

    @if ($election->status === \App\States\ElectionStatus::NOMINATION_OPEN->value)
        <section class="election-card">
            <div class="election-page-header"><div><h2>Candidate review</h2><p>{{ $candidateSummary['approved'] }} approved, {{ $candidateSummary['pending'] }} pending, {{ $candidateSummary['rejected'] }} rejected.</p></div><span class="election-status">{{ $candidateSummary['ready'] ? 'Ballot ready' : 'Review required' }}</span></div>
            @if ($candidateSummary['applications']->isEmpty())
                <p>No candidate applications have been submitted.</p>
            @elseif (! $isAdminView)
                <form method="post" action="{{ route('elections.candidates.review', $election) }}">
                    @csrf
                    <input type="hidden" name="version" value="{{ $election->version }}">
                    <div class="election-table-wrap"><table class="election-table"><thead><tr><th>Candidate</th><th>Registered</th><th>Decision</th></tr></thead><tbody>
                    @foreach ($candidateSummary['applications'] as $application)
                        @php $candidateName = $application->candidate?->user?->name ?? $application->candidate?->user?->username ?? 'Candidate #'.$application->candidate_id; @endphp
                        <tr><td>{{ $candidateName }}</td><td>{{ \Carbon\CarbonImmutable::parse($application->registered_at)->format('d M Y, H:i') }}</td><td><select name="decisions[{{ $application->getKey() }}]">@foreach (['Pending', 'Approved', 'Rejected'] as $status)<option value="{{ $status }}" @selected(strcasecmp($application->status, $status) === 0)>{{ $status }}</option>@endforeach</select></td></tr>
                    @endforeach
                    </tbody></table></div>
                    <label>Current password<input name="current_password" type="password" autocomplete="current-password" required></label>
                    <button class="election-button election-button-primary" type="submit">Save candidate decisions</button>
                </form>
            @endif
        </section>
    @endif

    <section class="election-card">
        <h2>Audit trail</h2>
        @if ($logs === [])
            <p>No lifecycle events recorded.</p>
        @else
            <div class="election-table-wrap"><table class="election-table"><thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Transition</th><th>Request</th></tr></thead><tbody>
            @foreach ($logs as $log)
                @php $actor = $log->actor(); @endphp
                <tr><td>{{ \Carbon\CarbonImmutable::parse($log->get('created_at'))->format('d M Y H:i') }}</td><td>{{ $actor?->name ?? $actor?->username ?? 'User #'.$log->get('actor_user_id') }}</td><td>{{ str($log->get('action'))->replace('_', ' ')->headline() }}</td><td><code>{{ $log->get('from_state') }}</code> &rarr; <code>{{ $log->get('to_state') }}</code></td><td><code>{{ $log->get('request_id') }}</code></td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </section>
</div>
@endsection
