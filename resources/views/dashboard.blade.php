{{-- PIC: Chew Zhexuan --}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="user-module">
    <section class="dashboard-hero">
        <div>
            <p class="eyebrow">Account dashboard</p>
            <h1>Welcome back, {{ $user->display_name }}.</h1>
            <p>Use one VoteTrust account to vote in some elections and organise others.</p>
        </div>
        <div class="account-summary">
            <span>Account status</span>
            <strong>{{ str($user->account_status ?? 'ACTIVE')->lower()->headline() }}</strong>
            <span class="account-summary__verification">Profile {{ $profileVerified ? 'verified' : 'verification required' }}</span>
            @unless ($profileVerified)
                <a href="{{ route('profile.verify') }}">Verify now</a>
            @endunless
        </div>
    </section>

    <section class="dashboard-info-grid" aria-label="VoteTrust account overview">
        <article class="dashboard-info-card dashboard-info-card--guide">
            <p class="eyebrow">How VoteTrust works</p>
            <h2>Clear election access, from enrolment to participation.</h2>
            <p>Your account keeps your voter access and organiser workspace in one place. Public elections follow their published eligibility rules, while private access is assigned separately.</p>
            <ol class="dashboard-steps">
                <li><span>1</span><div><strong>Access is determined</strong><small>Public eligibility is checked automatically; private access is assigned by an organiser.</small></div></li>
                <li><span>2</span><div><strong>Review your elections</strong><small>See election status and when voting becomes available.</small></div></li>
                <li><span>3</span><div><strong>Participate with confidence</strong><small>Vote once when an approved election opens.</small></div></li>
            </ol>
            <a class="button button-small" href="{{ route('elections.index') }}#voting-elections">Explore elections</a>
        </article>

        <article class="dashboard-info-card dashboard-info-card--activity">
            <p class="eyebrow">Your election space</p>
            <h2>Everything important, without the clutter.</h2>
            <p>Keep track of your access, open ballots, and elections you organise from one trusted account.</p>
            <dl class="dashboard-activity-stats">
                <div><dt>Voter access</dt><dd>{{ $overview['votingElections'] }}</dd></div>
                <div><dt>Ready to vote</dt><dd>{{ $overview['readyToVote'] }}</dd></div>
                <div><dt>Organised</dt><dd>{{ $overview['organizedElections'] }}</dd></div>
            </dl>
            <p class="dashboard-info-card__note">
                @if ($overview['readyToVote'] > 0)
                    You have {{ $overview['readyToVote'] }} open election{{ $overview['readyToVote'] === 1 ? '' : 's' }} awaiting your vote.
                @else
                    We will show an election here when your voter access is approved and voting opens.
                @endif
            </p>
            <a class="dashboard-info-link" href="{{ route('elections.index') }}">Open my elections <span aria-hidden="true">&rarr;</span></a>
        </article>
    </section>
</div>
@endsection
