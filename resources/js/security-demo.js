// PIC: Chew Zhexuan - local-only, server-backed security presentation lab.

const demo = document.querySelector('[data-security-demo]');

if (demo instanceof HTMLElement) {
    const actionUrl = demo.dataset.actionUrl;
    const maximumAttempts = Math.max(1, Number.parseInt(demo.dataset.maximumAttempts ?? '5', 10) || 5);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const modeButtons = [...demo.querySelectorAll('[data-demo-mode]')];
    const modeLabels = [...demo.querySelectorAll('[data-mode-label]')];
    const secureCode = [...demo.querySelectorAll('[data-code-secure]')];
    const vulnerableCode = [...demo.querySelectorAll('[data-code-vulnerable]')];
    const announcement = demo.querySelector('[data-demo-announcement]');
    const passwordInput = demo.querySelector('[data-demo-password]');

    const traceLabels = {
        'mode-select': 'Server reset the lab in the selected mode',
        'auth-request': 'Laravel received the sign-in POST request',
        'auth-password-check': 'Hash::check() compared the submitted password with the stored hash',
        'auth-account-check': 'The server checked whether the demo account was already locked',
        'auth-locked-reject': 'The locked account was denied even after password verification',
        'auth-record-failure': 'The server incremented the failed-attempt counter',
        'auth-lock-account': 'The configured limit was reached and the demo account was locked',
        'auth-untracked-failure': 'The vulnerable branch rejected the password without recording the failure',
        'auth-login-success': 'The matching password was accepted',
        'auth-reset': 'The server cleared the isolated authentication state',
        'session-request': 'Laravel received the demo logout POST request',
        'session-guard-logout': 'The isolated authenticated state was cleared',
        'session-invalidate': 'The old demo session token was invalidated',
        'session-regenerate-csrf': 'The demo CSRF state was regenerated',
        'session-redirect-only': 'The vulnerable branch redirected without invalidating the token',
        'session-replay-request': 'Laravel received the old-token replay request',
        'session-replay-granted': 'The unchanged token still granted access',
        'session-replay-denied': 'The invalidated token was denied',
        'session-reset': 'The server issued a fresh isolated demo session',
    };

    const text = (selector, value) => {
        const target = demo.querySelector(selector);
        if (target instanceof HTMLElement) {
            target.textContent = value;
        }
    };

    const setTone = (selector, tone) => {
        const target = demo.querySelector(selector);
        if (target instanceof HTMLElement) {
            target.dataset.tone = tone;
        }
    };

    const announce = (message) => {
        if (announcement instanceof HTMLElement) {
            announcement.textContent = message;
        }
    };

    const setBusy = (busy) => {
        demo.querySelectorAll('button').forEach((button) => {
            button.disabled = busy;
        });
        demo.dataset.busy = busy ? 'true' : 'false';
    };

    const applyMode = (mode) => {
        const selectedMode = mode === 'vulnerable' ? 'vulnerable' : 'secure';
        demo.dataset.mode = selectedMode;
        modeButtons.forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.demoMode === selectedMode ? 'true' : 'false');
        });
        modeLabels.forEach((label) => {
            label.textContent = selectedMode === 'secure' ? 'Secure practices on' : 'Vulnerable behavior on';
        });
        secureCode.forEach((block) => { block.hidden = selectedMode !== 'secure'; });
        vulnerableCode.forEach((block) => { block.hidden = selectedMode !== 'vulnerable'; });
    };

    const renderState = (state) => {
        applyMode(state.mode);

        text('[data-session-id]', state.session.token);
        text('[data-session-token]', state.session.tokenValid ? 'Valid' : 'Invalidated');
        text('[data-session-logout]', state.session.logoutResult);
        text('[data-session-csrf]', state.session.csrfResult);
        text('[data-session-replay]', state.session.replayResult);
        setTone('[data-session-token]', state.session.tokenValid ? (state.mode === 'vulnerable' ? 'danger' : 'neutral') : 'success');
        setTone('[data-session-replay]', state.session.replayResult === 'Access granted' ? 'danger' : (state.session.replayResult === 'Access denied' ? 'success' : 'neutral'));

        text('[data-auth-guesses]', String(state.authentication.totalAttempts));
        text('[data-auth-tracked]', state.mode === 'secure' ? `${state.authentication.failedAttempts} / ${maximumAttempts}` : 'Not tracked');
        text('[data-auth-account]', state.authentication.locked ? 'Locked' : 'Active');
        text('[data-auth-result]', state.authentication.result);
        setTone('[data-auth-account]', state.authentication.locked ? 'danger' : 'neutral');
        setTone('[data-auth-result]', state.authentication.authenticated ? 'success' : (state.authentication.locked ? 'danger' : 'neutral'));
    };

    const renderTrace = (trace, scope) => {
        demo.querySelectorAll('[data-comparison-code-scope]').forEach((snippet) => snippet.classList.remove('is-relevant'));
        const hasAction = trace.some((step) => step.endsWith('-request'));
        if (hasAction) {
            demo.querySelectorAll(`[data-comparison-code-scope="${scope}"]`).forEach((snippet) => {
                if (snippet.hidden) {
                    return;
                }
                const isLockoutMethod = snippet.dataset.comparisonCodeRole === 'lockout';
                const failureWasRecorded = trace.includes('auth-record-failure') || trace.includes('auth-lock-account');
                if (isLockoutMethod && ! failureWasRecorded) {
                    return;
                }
                snippet.classList.add('is-relevant');
                if (snippet instanceof HTMLDetailsElement) {
                    snippet.open = true;
                }
            });
        }

        const list = demo.querySelector(scope === 'session' ? '[data-session-trace]' : '[data-auth-trace]');
        if (!(list instanceof HTMLOListElement)) {
            return;
        }

        list.replaceChildren();
        trace.filter((step) => step !== 'mode-select').forEach((step) => {
            const item = document.createElement('li');
            item.textContent = traceLabels[step] ?? step;
            list.append(item);
        });
    };

    const renderFeedback = (scope, message, failed = false) => {
        const feedback = demo.querySelector(scope === 'session' ? '[data-session-feedback]' : '[data-auth-feedback]');
        if (!(feedback instanceof HTMLElement)) {
            return;
        }

        feedback.classList.toggle('is-error', failed);
        const title = feedback.querySelector('strong');
        const copy = feedback.querySelector('p');
        if (title instanceof HTMLElement) {
            title.textContent = failed ? 'Request failed' : 'Server response';
        }
        if (copy instanceof HTMLElement) {
            copy.textContent = message;
        }
    };

    const sendAction = async (action, payload = {}) => {
        if (! actionUrl) {
            throw new Error('The live demo endpoint is unavailable.');
        }

        setBusy(true);
        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ action, ...payload }),
            });

            if (! response.ok) {
                throw new Error(response.status === 419
                    ? 'The page expired. Refresh it and try again.'
                    : 'Laravel could not complete the demo request.');
            }

            return await response.json();
        } finally {
            setBusy(false);
        }
    };

    const perform = async (action, payload = {}) => {
        const scope = action.startsWith('session.') ? 'session' : 'authentication';
        try {
            const result = await sendAction(action, payload);
            renderState(result.state);
            renderTrace(result.trace, scope);
            renderFeedback(scope, result.message);
            announce(result.message);
            return result;
        } catch (error) {
            const message = error instanceof Error ? error.message : 'The request failed.';
            renderFeedback(scope, message, true);
            announce(message);
            return null;
        }
    };

    modeButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                const result = await sendAction('mode.change', { mode: button.dataset.demoMode });
                renderState(result.state);
                demo.querySelectorAll('[data-comparison-code-scope]').forEach((snippet) => snippet.classList.remove('is-relevant'));
                demo.querySelectorAll('.security-execution-trace').forEach((trace) => trace.replaceChildren());
                renderFeedback('session', result.message);
                renderFeedback('authentication', result.message);
                announce(result.message);
            } catch (error) {
                const message = error instanceof Error ? error.message : 'The mode could not be changed.';
                renderFeedback('authentication', message, true);
                announce(message);
            }
        });
    });

    demo.querySelectorAll('[data-live-action]').forEach((button) => {
        button.addEventListener('click', () => perform(button.dataset.liveAction));
    });

    demo.querySelector('[data-demo-login-form]')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!(passwordInput instanceof HTMLInputElement)) {
            return;
        }

        const password = passwordInput.value;
        await perform('authentication.attempt', { password });
        passwordInput.value = '';
        passwordInput.focus();
    });

    demo.querySelector('[data-toggle-demo-password]')?.addEventListener('click', (event) => {
        if (!(passwordInput instanceof HTMLInputElement) || !(event.currentTarget instanceof HTMLButtonElement)) {
            return;
        }

        const reveal = passwordInput.type === 'password';
        passwordInput.type = reveal ? 'text' : 'password';
        event.currentTarget.textContent = reveal ? 'Hide' : 'Show';
        event.currentTarget.setAttribute('aria-pressed', reveal ? 'true' : 'false');
    });

    demo.querySelectorAll('[data-fill-demo-password]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!(passwordInput instanceof HTMLInputElement)) {
                return;
            }
            passwordInput.value = button.dataset.fillDemoPassword === 'correct'
                ? button.dataset.correctPassword ?? ''
                : 'DefinitelyWrong!123';
            passwordInput.focus();
        });
    });

    applyMode(demo.dataset.mode);
}
