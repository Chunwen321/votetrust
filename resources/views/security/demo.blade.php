{{-- PIC: Chew Zhexuan - local-only, server-backed secure coding presentation lab. --}}
@extends('layouts.app')

@section('title', 'Secure coding comparison')

@section('content')
@php($authState = $demoState['authentication'])
@php($sessionState = $demoState['session'])
<div
    class="user-module security-demo"
    data-security-demo
    data-mode="{{ $demoState['mode'] }}"
    data-maximum-attempts="{{ $maximumAttempts }}"
    data-action-url="{{ route('security.demo.action') }}"
>
    <section class="security-demo__hero">
        <div class="security-demo__intro">
            <p class="eyebrow">Chew Zhexuan &middot; User module</p>
            <h1>Live secure-coding comparison</h1>
            <p>Perform each action yourself. Every attempt sends a real request to Laravel, updates isolated server-side state, and highlights the code path used. Input validation is intentionally excluded.</p>
        </div>

        <div class="security-demo__control-panel" aria-label="Security demonstration mode">
            <span class="security-demo__local-badge">Local live lab &middot; no real accounts</span>
            <div class="security-demo__mode-switch" role="group" aria-label="Choose comparison mode">
                <button type="button" data-demo-mode="secure" aria-pressed="{{ $demoState['mode'] === 'secure' ? 'true' : 'false' }}">
                    <span aria-hidden="true">&#10003;</span>
                    Secure mode
                </button>
                <button type="button" data-demo-mode="vulnerable" aria-pressed="{{ $demoState['mode'] === 'vulnerable' ? 'true' : 'false' }}">
                    <span aria-hidden="true">!</span>
                    Vulnerability mode
                </button>
            </div>
            <p class="security-demo__safety-note">The vulnerable branch runs only against the disposable identity and session shown here. VoteTrust's real login, users, and logout strategy remain unchanged.</p>
        </div>
    </section>

    <div class="security-demo__mode-banner" role="status">
        <span class="security-demo__mode-dot" aria-hidden="true"></span>
        <div>
            <strong data-mode-label>{{ $demoState['mode'] === 'secure' ? 'Secure practices on' : 'Vulnerable behavior on' }}</strong>
            <span data-secure-summary>Server-side controls stop repeated guessing and invalidate old session tokens.</span>
            <span data-vulnerable-summary>The isolated server branch omits those controls so you can observe the difference.</span>
        </div>
    </div>
    <p class="sr-only" aria-live="polite" data-demo-announcement></p>

    <div class="security-demo__practice-grid">
        <article class="security-practice-card">
            <header class="security-practice-card__header">
                <span class="security-practice-card__number">01</span>
                <div>
                    <p class="eyebrow">Session management</p>
                    <h2>Logout must fully terminate the session</h2>
                </div>
                <span class="security-practice-card__threat">Threat: unauthorised access</span>
            </header>

            <p class="security-practice-card__scenario">Scenario: an attacker copied an old token. Send the logout request, then personally replay that token against the live demo endpoint.</p>

            <div class="security-practice-card__workspace">
                <section class="security-simulator" aria-labelledby="session-simulator-title">
                    <div class="security-simulator__title">
                        <div>
                            <span>Live Laravel requests</span>
                            <h3 id="session-simulator-title">Isolated session replay</h3>
                        </div>
                        <span class="security-simulator__token" data-session-id>{{ $sessionState['token'] }}</span>
                    </div>

                    <dl class="security-simulator__states">
                        <div><dt>Old session token</dt><dd data-session-token data-tone="neutral">{{ $sessionState['tokenValid'] ? 'Valid' : 'Invalidated' }}</dd></div>
                        <div><dt>Logout operation</dt><dd data-session-logout data-tone="neutral">{{ $sessionState['logoutResult'] }}</dd></div>
                        <div><dt>CSRF token</dt><dd data-session-csrf data-tone="neutral">{{ $sessionState['csrfResult'] }}</dd></div>
                        <div><dt>Replay result</dt><dd data-session-replay data-tone="neutral">{{ $sessionState['replayResult'] }}</dd></div>
                    </dl>

                    <div class="security-simulator__actions">
                        <button type="button" data-live-action="session.logout">1. Send logout request</button>
                        <button class="button-quiet" type="button" data-live-action="session.replay">2. Replay old token</button>
                        <button class="button-quiet security-simulator__reset" type="button" data-live-action="session.reset">Reset session</button>
                    </div>

                    <div class="security-live-feedback" data-session-feedback role="status">
                        <strong>Ready</strong>
                        <p>Choose an action to send it to the server.</p>
                    </div>
                    <ol class="security-execution-trace" data-session-trace aria-label="Executed server code path"></ol>
                </section>

                <section class="security-code" aria-labelledby="session-code-title">
                    <div class="security-code__heading">
                        <div>
                            <span>Secure implementation vs controls removed</span>
                            <h3 id="session-code-title">Session code comparison</h3>
                        </div>
                        <code>Mode controls the code shown</code>
                    </div>

                    <div class="security-code__boundary">
                        <strong data-code-secure>Secure mode: exact production source</strong>
                        <strong data-code-vulnerable hidden>Vulnerability mode: security operations excluded</strong>
                        <span>The action runs through the isolated demo; this panel shows the matching implementation to explain.</span>
                    </div>
                    <div class="security-source-stack">
                        @include('security._source-snippet', ['snippet' => $sourceSnippets['productionSession'], 'scope' => 'session', 'role' => 'session', 'mode' => 'secure', 'open' => true])
                        @include('security._source-snippet', ['snippet' => $sourceSnippets['vulnerableSession'], 'scope' => 'session', 'role' => 'session', 'mode' => 'vulnerable', 'open' => true])
                    </div>
                    <p class="security-code__explanation" data-code-secure>The exact real method logs out the guard, invalidates the session, and regenerates the CSRF token.</p>
                    <p class="security-code__warning" data-code-vulnerable hidden>The vulnerable comparison removes those three operations, leaving the redirected session usable.</p>
                </section>
            </div>
        </article>

        <article class="security-practice-card">
            <header class="security-practice-card__header">
                <span class="security-practice-card__number">02</span>
                <div>
                    <p class="eyebrow">Authentication &amp; password management</p>
                    <h2>Key in the password and attempt a real sign-in</h2>
                </div>
                <span class="security-practice-card__threat">Threat: brute-force attack</span>
            </header>

            <p class="security-practice-card__scenario">Submit your own guesses to a disposable demo identity. Secure mode locks it after {{ $maximumAttempts }} failures; vulnerable mode leaves unlimited attempts available.</p>

            <div class="security-practice-card__workspace">
                <section class="security-simulator" aria-labelledby="auth-simulator-title">
                    <div class="security-simulator__title">
                        <div>
                            <span>Live password verification</span>
                            <h3 id="auth-simulator-title">Manual sign-in attempts</h3>
                        </div>
                        <span class="security-simulator__token">Limit: {{ $maximumAttempts }}</span>
                    </div>

                    <form class="security-demo-login" data-demo-login-form>
                        <div class="auth-field">
                            <label for="demo-email">Disposable demo identity</label>
                            <input id="demo-email" type="email" value="{{ $demoEmail }}" readonly>
                        </div>
                        <div class="auth-field">
                            <label for="demo-password">Password guess</label>
                            <div class="security-demo-login__password">
                                <input id="demo-password" name="password" type="password" autocomplete="off" placeholder="Type a wrong or correct password" required data-demo-password>
                                <button class="button-quiet" type="button" data-toggle-demo-password aria-pressed="false">Show</button>
                            </div>
                        </div>
                        <button type="submit">Attempt sign in</button>
                    </form>

                    <div class="security-demo-login__helpers">
                        <button class="button-quiet" type="button" data-fill-demo-password="wrong">Fill wrong password</button>
                        <button class="button-quiet" type="button" data-fill-demo-password="correct" data-correct-password="{{ $demoPassword }}">Fill correct password</button>
                        <span>Demo password: <code>{{ $demoPassword }}</code></span>
                    </div>

                    <dl class="security-simulator__states">
                        <div><dt>Total attempts sent</dt><dd data-auth-guesses data-tone="neutral">{{ $authState['totalAttempts'] }}</dd></div>
                        <div><dt>Failures recorded</dt><dd data-auth-tracked data-tone="neutral">{{ $demoState['mode'] === 'secure' ? $authState['failedAttempts'].' / '.$maximumAttempts : 'Not tracked' }}</dd></div>
                        <div><dt>Demo account</dt><dd data-auth-account data-tone="neutral">{{ $authState['locked'] ? 'Locked' : 'Active' }}</dd></div>
                        <div><dt>Server result</dt><dd data-auth-result data-tone="neutral">{{ $authState['result'] }}</dd></div>
                    </dl>

                    <div class="security-simulator__actions">
                        <button class="button-quiet security-simulator__reset" type="button" data-live-action="authentication.reset">Reset demo account</button>
                    </div>

                    <div class="security-live-feedback" data-auth-feedback role="status">
                        <strong>Ready</strong>
                        <p>Enter a password and submit the form.</p>
                    </div>
                    <ol class="security-execution-trace" data-auth-trace aria-label="Executed server code path"></ol>
                </section>

                <section class="security-code" aria-labelledby="auth-code-title">
                    <div class="security-code__heading">
                        <div>
                            <span>Secure implementation vs lockout removed</span>
                            <h3 id="auth-code-title">Authentication code comparison</h3>
                        </div>
                        <code>Mode controls the code shown</code>
                    </div>

                    <div class="security-code__boundary">
                        <strong data-code-secure>Secure mode: exact production source</strong>
                        <strong data-code-vulnerable hidden>Vulnerability mode: failed-attempt protection excluded</strong>
                        <span>Password attempts run against the isolated demo identity; this panel shows the matching implementation to explain.</span>
                    </div>
                    <div class="security-source-stack">
                        @include('security._source-snippet', ['snippet' => $sourceSnippets['productionAuthentication'], 'scope' => 'authentication', 'role' => 'authentication', 'mode' => 'secure', 'open' => true])
                        @include('security._source-snippet', ['snippet' => $sourceSnippets['productionLockout'], 'scope' => 'authentication', 'role' => 'lockout', 'mode' => 'secure'])
                        @include('security._source-snippet', ['snippet' => $sourceSnippets['vulnerableAuthentication'], 'scope' => 'authentication', 'role' => 'authentication', 'mode' => 'vulnerable', 'open' => true])
                    </div>
                    <p class="security-code__explanation" data-code-secure>The exact real methods check account access, record every failure, and disable the account at the configured threshold.</p>
                    <p class="security-code__warning" data-code-vulnerable hidden>The vulnerable comparison still verifies passwords, but removes allows(), recordFailure(), the counter, and account lockout.</p>
                </section>
            </div>
        </article>
    </div>

    <section class="security-demo__talk-track" aria-labelledby="talk-track-title">
        <div>
            <p class="eyebrow">Suggested presentation flow</p>
            <h2 id="talk-track-title">Demonstrate actions, outcomes, and code together</h2>
        </div>
        <ol>
            <li><strong>Secure logout:</strong> send the logout request, replay the token, and point to each highlighted line.</li>
            <li><strong>Vulnerable logout:</strong> repeat it and show the live server granting access to the unchanged token.</li>
            <li><strong>Secure authentication:</strong> type a wrong password {{ $maximumAttempts }} times and show the account become locked.</li>
            <li><strong>Vulnerable authentication:</strong> enter several wrong guesses, then <code>{{ $demoPassword }}</code>; access is granted because failures were never tracked.</li>
        </ol>
    </section>
</div>
@endsection
