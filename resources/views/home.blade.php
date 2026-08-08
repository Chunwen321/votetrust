{{-- PIC: Goh Chun Wen, Chew Zhexuan, Wong Shun Bin, Low Wen Kai - shared VoteTrust entry page. --}}
@extends('layouts.app')

@section('title', 'Home')

@section('content')
<section class="home-page">
    <header class="page-heading">
        <p class="eyebrow">SDG 16 &middot; Peace, justice and strong institutions</p>
        <h1>One trusted place to organise, contest, and vote.</h1>
        <p>VoteTrust brings the four migrated Laravel modules together while keeping each teammate integration behind a tracked REST web-service contract.</p>
        <div class="home-actions">
            @auth
                <a class="button button--primary" href="{{ route('dashboard') }}">Open my dashboard</a>
                <a class="button button--quiet" href="{{ route('voting.index') }}">Go to voting</a>
            @else
                <a class="button button--primary" href="{{ route('login') }}">Sign in</a>
                <a class="button button--quiet" href="{{ route('register') }}">Create account</a>
            @endauth
        </div>
    </header>

    <div class="card-grid home-modules" aria-label="VoteTrust modules">
        <article class="card">
            <p class="eyebrow">Chew Zhexuan &middot; Strategy</p>
            <h2>User</h2>
            <p>Secure registration, login protection, complete logout, voter eligibility, profile, and administration.</p>
            <a class="back-link" href="{{ auth()->check() ? route('dashboard') : route('login') }}">Open User module &rarr;</a>
        </article>

        <article class="card">
            <p class="eyebrow">Low Wen Kai &middot; State</p>
            <h2>Election</h2>
            <p>Create elections and move them safely through payment, approval, nomination, voting, and closure.</p>
            <a class="back-link" href="{{ auth()->check() ? route('elections.index') : route('login') }}">Open Election module &rarr;</a>
        </article>

        <article class="card">
            <p class="eyebrow">Wong Shun Bin &middot; Proxy</p>
            <h2>Candidate</h2>
            <p>Submit protected applications and documents, track payment status, and manage candidate profiles.</p>
            <a class="back-link" href="{{ auth()->check() ? route('candidate.applications.create') : route('login') }}">Open Candidate module &rarr;</a>
        </article>

        <article class="card">
            <p class="eyebrow">Goh Chun Wen &middot; Decorator</p>
            <h2>Voting Process</h2>
            <p>Consume authoritative eligibility and election APIs, cast a private vote, and prevent concurrent double voting.</p>
            <a class="back-link" href="{{ auth()->check() ? route('voting.index') : route('login') }}">Open Voting module &rarr;</a>
        </article>
    </div>
</section>
@endsection
