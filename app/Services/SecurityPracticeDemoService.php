<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class SecurityPracticeDemoService
{
    public const DEMO_EMAIL = 'security.demo@votetrust.local';

    public const DEMO_PASSWORD = 'VoteTrustDemo!2026';

    private const DEMO_PASSWORD_HASH = '$2y$10$4tV8pWRdAK./zAq.MUZpnerW5pSctFYX9PsOBN1O22vYsJusHMpdq';

    private const SESSION_KEY = 'security_practice_demo';

    /** @return array<string, mixed> */
    public function state(Request $request): array
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state)) {
            $state = $this->freshState();
            $request->session()->put(self::SESSION_KEY, $state);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{state:array<string, mixed>,trace:list<string>,message:string}
     */
    public function perform(Request $request, string $action, array $input = []): array
    {
        $state = $this->state($request);

        [$state, $trace, $message] = match ($action) {
            'mode.change' => $this->changeMode($input),
            'authentication.attempt' => $this->attemptAuthentication($state, $input),
            'authentication.reset' => $this->resetAuthentication($state),
            'session.logout' => $this->terminateDemoSession($state),
            'session.replay' => $this->replayDemoSession($state),
            'session.reset' => $this->resetDemoSession($state),
            default => abort(422, 'Unknown security demo action.'),
        };

        $request->session()->put(self::SESSION_KEY, $state);

        return compact('state', 'trace', 'message');
    }

    /** @return array{array<string, mixed>,list<string>,string} */
    private function changeMode(array $input): array
    {
        $mode = ($input['mode'] ?? null) === 'vulnerable' ? 'vulnerable' : 'secure';

        return [
            $this->freshState($mode),
            ['mode-select'],
            $mode === 'secure'
                ? 'Secure server-side controls are active for the isolated demo identity.'
                : 'Vulnerable server-side behavior is active only for the isolated demo identity.',
        ];
    }

    /** @return array{array<string, mixed>,list<string>,string} */
    private function attemptAuthentication(array $state, array $input): array
    {
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $secure = $state['mode'] === 'secure';
        $maximum = $this->maximumAttempts();
        $trace = ['auth-request', 'auth-password-check'];

        $state['authentication']['totalAttempts']++;
        $passwordMatches = Hash::check($password, self::DEMO_PASSWORD_HASH);

        if ($secure) {
            $trace[] = 'auth-account-check';

            if ($state['authentication']['locked']) {
                $state['authentication']['result'] = 'Account locked — access denied';
                $trace[] = 'auth-locked-reject';

                return [$state, $trace, 'The password was checked, but the locked account cannot sign in.'];
            }
        }

        if (! $passwordMatches) {
            if (! $secure) {
                $state['authentication']['result'] = 'Wrong password — unlimited retries remain';
                $trace[] = 'auth-untracked-failure';

                return [$state, $trace, 'Wrong password rejected, but no failed-attempt counter was updated.'];
            }

            $state['authentication']['failedAttempts']++;
            $trace[] = 'auth-record-failure';

            if ($state['authentication']['failedAttempts'] >= $maximum) {
                $state['authentication']['locked'] = true;
                $state['authentication']['result'] = 'Account locked — attack stopped';
                $trace[] = 'auth-lock-account';

                return [$state, $trace, "The {$maximum}th failure locked the isolated demo account."];
            }

            $remaining = $maximum - $state['authentication']['failedAttempts'];
            $state['authentication']['result'] = 'Wrong password rejected';

            return [$state, $trace, trans_choice(
                ':count attempt remains before lockout.|:count attempts remain before lockout.',
                $remaining,
                ['count' => $remaining],
            )];
        }

        $state['authentication']['authenticated'] = true;
        $state['authentication']['failedAttempts'] = 0;
        $state['authentication']['result'] = 'Password accepted — demo access granted';
        $trace[] = 'auth-login-success';

        return [$state, $trace, 'The submitted password matched the stored hash.'];
    }

    /** @return array{array<string, mixed>,list<string>,string} */
    private function resetAuthentication(array $state): array
    {
        $state['authentication'] = $this->freshAuthenticationState();

        return [$state, ['auth-reset'], 'The isolated demo account was reset.'];
    }

    /** @return array{array<string, mixed>,list<string>,string} */
    private function terminateDemoSession(array $state): array
    {
        if ($state['mode'] === 'vulnerable') {
            $state['session']['logoutResult'] = 'Redirect only';
            $state['session']['replayResult'] = 'Not tested';

            return [
                $state,
                ['session-request', 'session-redirect-only'],
                'The response redirected, but the isolated session token remains valid.',
            ];
        }

        $state['session']['tokenValid'] = false;
        $state['session']['logoutResult'] = 'Guard cleared + session invalidated';
        $state['session']['csrfResult'] = 'Token regenerated';
        $state['session']['replayResult'] = 'Not tested';

        return [
            $state,
            ['session-request', 'session-guard-logout', 'session-invalidate', 'session-regenerate-csrf'],
            'Laravel handled the request and invalidated the isolated demo session.',
        ];
    }

    /** @return array{array<string, mixed>,list<string>,string} */
    private function replayDemoSession(array $state): array
    {
        $granted = (bool) $state['session']['tokenValid'];
        $state['session']['replayResult'] = $granted ? 'Access granted' : 'Access denied';

        return [
            $state,
            ['session-replay-request', $granted ? 'session-replay-granted' : 'session-replay-denied'],
            $granted
                ? 'Replay succeeded because the old demo token is still valid.'
                : 'Replay failed because the old demo token was invalidated.',
        ];
    }

    /** @return array{array<string, mixed>,list<string>,string} */
    private function resetDemoSession(array $state): array
    {
        $state['session'] = $this->freshSessionState();

        return [$state, ['session-reset'], 'A fresh isolated demo session was created.'];
    }

    /** @return array<string, mixed> */
    private function freshState(string $mode = 'secure'): array
    {
        return [
            'mode' => $mode,
            'authentication' => $this->freshAuthenticationState(),
            'session' => $this->freshSessionState(),
        ];
    }

    /** @return array<string, mixed> */
    private function freshAuthenticationState(): array
    {
        return [
            'totalAttempts' => 0,
            'failedAttempts' => 0,
            'locked' => false,
            'authenticated' => false,
            'result' => 'Ready for a sign-in attempt',
        ];
    }

    /** @return array<string, mixed> */
    private function freshSessionState(): array
    {
        return [
            'token' => 'DEMO-SID-'.Str::upper(Str::random(6)),
            'tokenValid' => true,
            'logoutResult' => 'Not run',
            'csrfResult' => 'Original token',
            'replayResult' => 'Not tested',
        ];
    }

    private function maximumAttempts(): int
    {
        return max(1, (int) config('votetrust-user.auth.max_failed_attempts', 5));
    }
}
