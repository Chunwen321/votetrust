@extends('layouts.app')

@section('title', 'Frequently asked questions')

@section('content')
<div class="faq-page">
    <header class="faq-hero" aria-labelledby="faq-title">
        <div>
            <p class="eyebrow">Help centre</p>
            <h1 id="faq-title">Frequently asked questions</h1>
            <p>Quick answers about accounts, election access, candidate applications, voting, and results.</p>
        </div>
        <div class="faq-hero__actions">
            @auth
                <a class="button button--light" href="{{ route(auth()->user()->isAdmin() ? 'admin.dashboard' : 'dashboard') }}">Go to dashboard</a>
            @else
                <a class="button button--light" href="{{ route('register') }}">Create account</a>
                <a class="faq-hero__link" href="{{ route('login') }}">Sign in</a>
            @endauth
        </div>
    </header>

    <section class="faq-section" aria-labelledby="faq-list-title">
        <div class="faq-section__heading">
            <p class="eyebrow">VoteTrust support</p>
            <h2 id="faq-list-title">What would you like to know?</h2>
            <p>Open a question below to see the answer.</p>
        </div>

        <div class="faq-list">
            <details open>
                <summary>What can I do with a VoteTrust account?</summary>
                <div class="faq-answer">
                    <div class="faq-answer__inner">
                        <p>A registered member can manage their profile and, after verification, organise elections, apply as a candidate, and vote in elections for which they are eligible.</p>
                    </div>
                </div>
            </details>
            <details>
                <summary>Why does my profile need to be verified?</summary>
                <div class="faq-answer">
                    <div class="faq-answer__inner">
                        <p>Profile verification unlocks election management, candidate applications, and voting. It also gives VoteTrust the identity and age information needed to apply election eligibility rules.</p>
                    </div>
                </div>
            </details>
            <details>
                <summary>What is the difference between a public and private election?</summary>
                <div class="faq-answer">
                    <div class="faq-answer__inner">
                        <p>Public elections use their configured age and nationality rules. Private elections are limited to registered members whom the organiser has added to the approved voter list.</p>
                    </div>
                </div>
            </details>
            <details>
                <summary>How do candidate applications work?</summary>
                <div class="faq-answer">
                    <div class="faq-answer__inner">
                        <p>When nominations are open, an eligible verified member can submit the required candidate details and deposit information. The election organiser then reviews the application before the ballot is confirmed.</p>
                    </div>
                </div>
            </details>
            <details>
                <summary>Can I vote more than once or change my vote?</summary>
                <div class="faq-answer">
                    <div class="faq-answer__inner">
                        <p>No. VoteTrust accepts one recorded vote per voter for each election and rejects repeat submissions, so review your selection carefully before submitting it.</p>
                    </div>
                </div>
            </details>
            <details>
                <summary>When can election results be viewed?</summary>
                <div class="faq-answer">
                    <div class="faq-answer__inner">
                        <p>Vote totals are released only after the election has been closed. Results remain unavailable while nomination or voting is still in progress.</p>
                    </div>
                </div>
            </details>
        </div>
    </section>
</div>
@endsection
