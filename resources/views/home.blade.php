@extends('layouts.app')

@section('title', 'Trusted online elections')

@section('content')
<div class="home-page">
    <section class="home-hero" aria-labelledby="home-title">
        <div class="home-hero__content">
            <p class="home-pill"><span aria-hidden="true"></span> A clearer way to elect</p>
            <h1 id="home-title">Run elections people can trust.</h1>
            <p class="home-hero__lead">VoteTrust brings organisers, candidates, and voters into one guided online election experience—from setup and nomination to a valid vote.</p>

            <div class="home-actions">
                @auth
                    <a class="button button--primary button--large" href="{{ route('dashboard') }}">Open my dashboard</a>
                    <a class="button button--quiet button--large" href="{{ route('elections.index') }}#voting-elections">View open elections</a>
                @else
                    <a class="button button--primary button--large" href="{{ route('register') }}">Create your account</a>
                    <a class="button button--quiet button--large" href="#how-it-works">See how it works</a>
                @endauth
            </div>

            <ul class="home-hero__assurances" aria-label="VoteTrust highlights">
                <li><span aria-hidden="true">✓</span> Guided election setup</li>
                <li><span aria-hidden="true">✓</span> Eligibility checks</li>
                <li><span aria-hidden="true">✓</span> Duplicate-vote protection</li>
            </ul>
        </div>

        <div class="election-preview" aria-label="Example election journey">
            <div class="election-preview__topline">
                <div>
                    <p class="eyebrow">Election journey</p>
                    <h2>Everything stays on track</h2>
                </div>
                <span class="live-badge"><span aria-hidden="true"></span> Voting open</span>
            </div>

            <ol class="journey-list">
                <li class="is-complete">
                    <span class="journey-list__number">01</span>
                    <span><strong>Election created</strong><small>Details and eligibility defined</small></span>
                    <span class="journey-list__check" aria-hidden="true">✓</span>
                </li>
                <li class="is-complete">
                    <span class="journey-list__number">02</span>
                    <span><strong>Candidates confirmed</strong><small>Applications reviewed and approved</small></span>
                    <span class="journey-list__check" aria-hidden="true">✓</span>
                </li>
                <li class="is-current">
                    <span class="journey-list__number">03</span>
                    <span><strong>Eligible voters vote</strong><small>One recorded choice per voter</small></span>
                    <span class="journey-list__pulse" aria-hidden="true"></span>
                </li>
                <li>
                    <span class="journey-list__number">04</span>
                    <span><strong>Election closes</strong><small>A clear end to the voting period</small></span>
                </li>
            </ol>
        </div>
    </section>

    <section class="home-audiences" aria-label="Who VoteTrust is for">
        <div class="home-audiences__track">
            <div class="home-audiences__group">
                <article>
                    <span class="audience-icon" aria-hidden="true">O</span>
                    <div><strong>For organisers</strong><p>Create and manage an election through each stage.</p></div>
                </article>
                <article>
                    <span class="audience-icon audience-icon--violet" aria-hidden="true">C</span>
                    <div><strong>For candidates</strong><p>Apply, submit details, and follow your application.</p></div>
                </article>
                <article>
                    <span class="audience-icon audience-icon--green" aria-hidden="true">V</span>
                    <div><strong>For voters</strong><p>Check eligibility and cast a straightforward vote.</p></div>
                </article>
            </div>
            <div class="home-audiences__group" aria-hidden="true">
                <article>
                    <span class="audience-icon" aria-hidden="true">O</span>
                    <div><strong>For organisers</strong><p>Create and manage an election through each stage.</p></div>
                </article>
                <article>
                    <span class="audience-icon audience-icon--violet" aria-hidden="true">C</span>
                    <div><strong>For candidates</strong><p>Apply, submit details, and follow your application.</p></div>
                </article>
                <article>
                    <span class="audience-icon audience-icon--green" aria-hidden="true">V</span>
                    <div><strong>For voters</strong><p>Check eligibility and cast a straightforward vote.</p></div>
                </article>
            </div>
        </div>
    </section>

    <section class="home-section home-intro" id="why-votetrust" aria-labelledby="why-title">
        <div class="section-heading">
            <p class="eyebrow">Why VoteTrust</p>
            <h2 id="why-title">Built for confidence at every step</h2>
            <p>Online elections should feel clear to the person running them and the person voting in them. VoteTrust keeps the process organised, controlled, and easy to follow.</p>
        </div>

        <div class="benefit-grid">
            <article class="benefit-card">
                <span class="benefit-card__number">01</span>
                <h3>A guided process</h3>
                <p>Move from draft through approval, nomination, voting, and closure with defined election stages.</p>
            </article>
            <article class="benefit-card">
                <span class="benefit-card__number">02</span>
                <h3>Controlled participation</h3>
                <p>Eligibility rules and approved candidacies help ensure the right people take part in each election.</p>
            </article>
            <article class="benefit-card">
                <span class="benefit-card__number">03</span>
                <h3>Protected vote submission</h3>
                <p>Votes are accepted only while voting is open, with safeguards against repeat or concurrent submissions.</p>
            </article>
        </div>
    </section>

    <section class="home-section home-process" id="how-it-works" aria-labelledby="process-title">
        <div class="section-heading section-heading--center">
            <p class="eyebrow">How it works</p>
            <h2 id="process-title">From an idea to a completed election</h2>
            <p>A shared platform keeps every participant informed and gives each role a focused place to act.</p>
        </div>

        <ol class="process-grid">
            <li>
                <span>1</span>
                <h3>Set up the election</h3>
                <p>Add the election details, dates, and participation requirements.</p>
            </li>
            <li>
                <span>2</span>
                <h3>Confirm participation</h3>
                <p>Review candidates and make the election available to eligible voters.</p>
            </li>
            <li>
                <span>3</span>
                <h3>Open voting</h3>
                <p>Voters view the approved ballot and submit one choice during the voting period.</p>
            </li>
            <li>
                <span>4</span>
                <h3>Close with clarity</h3>
                <p>End voting at the defined time and keep the election record in one place.</p>
            </li>
        </ol>
    </section>

    <section class="home-cta" aria-labelledby="cta-title">
        <div>
            <p class="eyebrow">Your election starts here</p>
            <h2 id="cta-title">Make the next vote easier to run—and easier to trust.</h2>
        </div>
        <div class="home-cta__actions">
            @auth
                <a class="button button--light button--large" href="{{ route('elections.index') }}">Manage elections</a>
            @else
                <a class="button button--light button--large" href="{{ route('register') }}">Get started</a>
                <a class="home-cta__link" href="{{ route('login') }}">Already have an account? Sign in</a>
            @endauth
        </div>
    </section>
</div>
@endsection
