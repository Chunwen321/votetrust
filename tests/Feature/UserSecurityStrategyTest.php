<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Security\Authentication\DisableAccountAfterFailedAttemptsStrategy;
use App\Security\Authentication\LoginProtectionStrategy;
use App\Security\Session\FullSessionTerminationStrategy;
use App\Security\Session\SessionTerminationStrategy;
use App\Services\SecurityPracticeSourcePresenter;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserSecurityStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_laravel_composes_the_planned_user_security_strategies(): void
    {
        self::assertInstanceOf(
            DisableAccountAfterFailedAttemptsStrategy::class,
            $this->app->make(LoginProtectionStrategy::class),
        );
        self::assertInstanceOf(
            FullSessionTerminationStrategy::class,
            $this->app->make(SessionTerminationStrategy::class),
        );
    }

    public function test_user_service_delegates_login_outcomes_to_the_configured_strategy(): void
    {
        $strategy = new class implements LoginProtectionStrategy
        {
            public int $allowChecks = 0;

            public int $failures = 0;

            public int $successes = 0;

            public function allows(User $user): bool
            {
                $this->allowChecks++;

                return true;
            }

            public function recordFailure(User $user): void
            {
                $this->failures++;
            }

            public function recordSuccess(User $user): void
            {
                $this->successes++;
            }
        };
        $this->app->instance(LoginProtectionStrategy::class, $strategy);

        $user = User::factory()->create([
            'email' => 'strategy.user@example.test',
            'password' => Hash::make('Correct!Password123'),
            'account_status' => 'ACTIVE',
        ]);
        $users = $this->app->make(UserService::class);

        self::assertFalse($users->authenticate('strategy.user@example.test', 'Wrong!Password123'));
        self::assertTrue($users->authenticate('strategy.user@example.test', 'Correct!Password123'));
        self::assertSame(3, $strategy->allowChecks);
        self::assertSame(1, $strategy->failures);
        self::assertSame(1, $strategy->successes);
        $this->assertAuthenticatedAs($user);
    }

    public function test_default_login_strategy_disables_an_account_at_the_configured_threshold(): void
    {
        config()->set('votetrust-user.auth.max_failed_attempts', 2);
        $user = User::factory()->create([
            'account_status' => 'ACTIVE',
            'failed_login_attempts' => 0,
            'account_disabled_at' => null,
        ]);
        $strategy = $this->app->make(LoginProtectionStrategy::class);

        $strategy->recordFailure($user);
        $strategy->recordFailure($user);

        $user->refresh();
        self::assertSame(2, $user->failed_login_attempts);
        self::assertSame('DISABLED', $user->account_status);
        self::assertNotNull($user->account_disabled_at);
        self::assertFalse($strategy->allows($user));
    }

    public function test_logout_delegates_to_full_session_termination_and_removes_session_data(): void
    {
        $delegate = $this->app->make(FullSessionTerminationStrategy::class);
        $strategy = new class($delegate) implements SessionTerminationStrategy
        {
            public bool $called = false;

            public function __construct(private readonly SessionTerminationStrategy $delegate) {}

            public function terminate(Request $request): void
            {
                $this->called = true;
                $this->delegate->terminate($request);
            }
        };
        $this->app->instance(SessionTerminationStrategy::class, $strategy);

        $user = User::factory()->create();
        $oldToken = 'known-old-csrf-token';

        $response = $this->actingAs($user)
            ->withSession([
                '_token' => $oldToken,
                'sensitive-session-value' => 'must-be-removed',
            ])
            ->post(route('logout'), ['_token' => $oldToken]);

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('sensitive-session-value');
        $this->assertGuest();
        self::assertTrue($strategy->called);
        self::assertNotSame($oldToken, session()->token());
    }

    public function test_security_practice_demo_is_disabled_unless_the_local_flag_is_enabled(): void
    {
        config()->set('votetrust-user.security_demo.enabled', false);

        $this->actingAs(User::factory()->create())
            ->get(route('security.demo'))
            ->assertNotFound();
    }

    public function test_authenticated_presenter_can_open_the_safe_security_comparison_demo(): void
    {
        config()->set('votetrust-user.security_demo.enabled', true);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('security.demo'));

        $response->assertOk()
            ->assertSee('Live secure-coding comparison')
            ->assertSee('Input validation is intentionally excluded')
            ->assertSee('Session management')
            ->assertSee('Authentication &amp; password management', false)
            ->assertSee('data-security-demo', false)
            ->assertSee('data-demo-login-form', false)
            ->assertSee('VoteTrustDemo!2026')
            ->assertSee('Vulnerability mode')
            ->assertSee('no real accounts')
            ->assertSee('Exact secure source')
            ->assertSee('Security removed')
            ->assertSee('Exact code used by the real VoteTrust application')
            ->assertSee('Demo-only version with the selected security controls removed')
            ->assertSee('Secure mode: exact production source')
            ->assertSee('Vulnerability mode: failed-attempt protection excluded')
            ->assertSee('$request->session()->invalidate();')
            ->assertSee('$this->loginProtection->recordFailure($user);')
            ->assertDontSee('SecurityPracticeDemoService.php')
            ->assertDontSee('Executed by this live lab')
            ->assertDontSee('Illustrative anti-pattern');
    }

    public function test_vulnerable_comparison_removes_only_the_selected_security_controls(): void
    {
        $snippets = $this->app->make(SecurityPracticeSourcePresenter::class)->snippets();
        $vulnerableSession = $snippets['vulnerableSession']['code'];
        $vulnerableAuthentication = $snippets['vulnerableAuthentication']['code'];

        self::assertStringNotContainsString('guard()->logout()', $vulnerableSession);
        self::assertStringNotContainsString('session()->invalidate()', $vulnerableSession);
        self::assertStringNotContainsString('session()->regenerateToken()', $vulnerableSession);
        self::assertStringContainsString('Hash::check', $vulnerableAuthentication);
        self::assertStringContainsString('Auth::login', $vulnerableAuthentication);
        self::assertStringNotContainsString('loginProtection', $vulnerableAuthentication);
        self::assertStringNotContainsString('failed_login_attempts', $vulnerableAuthentication);
        self::assertStringNotContainsString('account_status', $vulnerableAuthentication);
    }

    public function test_live_demo_secure_mode_records_failures_and_locks_the_isolated_account(): void
    {
        config()->set('votetrust-user.security_demo.enabled', true);
        config()->set('votetrust-user.auth.max_failed_attempts', 2);
        $this->actingAs(User::factory()->create());

        $this->postJson(route('security.demo.action'), [
            'action' => 'authentication.attempt',
            'password' => 'Wrong!Password123',
        ])->assertOk()
            ->assertJsonPath('state.authentication.failedAttempts', 1)
            ->assertJsonPath('state.authentication.locked', false)
            ->assertJsonPath('trace.2', 'auth-account-check')
            ->assertJsonPath('trace.3', 'auth-record-failure');

        $this->postJson(route('security.demo.action'), [
            'action' => 'authentication.attempt',
            'password' => 'Still!Wrong123',
        ])->assertOk()
            ->assertJsonPath('state.authentication.failedAttempts', 2)
            ->assertJsonPath('state.authentication.locked', true)
            ->assertJsonPath('trace.4', 'auth-lock-account');

        $this->postJson(route('security.demo.action'), [
            'action' => 'authentication.attempt',
            'password' => 'VoteTrustDemo!2026',
        ])->assertOk()
            ->assertJsonPath('state.authentication.authenticated', false)
            ->assertJsonPath('state.authentication.result', 'Account locked — access denied')
            ->assertJsonPath('trace.3', 'auth-locked-reject');

        $this->postJson(route('security.demo.action'), [
            'action' => 'authentication.reset',
        ])->assertOk()
            ->assertJsonPath('state.authentication.failedAttempts', 0)
            ->assertJsonPath('state.authentication.locked', false);
    }

    public function test_live_demo_vulnerable_mode_allows_manual_guesses_without_lockout(): void
    {
        config()->set('votetrust-user.security_demo.enabled', true);
        config()->set('votetrust-user.auth.max_failed_attempts', 2);
        $this->actingAs(User::factory()->create());

        $this->postJson(route('security.demo.action'), [
            'action' => 'mode.change',
            'mode' => 'vulnerable',
        ])->assertOk()->assertJsonPath('state.mode', 'vulnerable');

        foreach (['Wrong!Password123', 'Still!Wrong123', 'Again!Wrong123'] as $password) {
            $this->postJson(route('security.demo.action'), [
                'action' => 'authentication.attempt',
                'password' => $password,
            ])->assertOk()
                ->assertJsonPath('state.authentication.failedAttempts', 0)
                ->assertJsonPath('state.authentication.locked', false)
                ->assertJsonPath('trace.2', 'auth-untracked-failure');
        }

        $this->postJson(route('security.demo.action'), [
            'action' => 'authentication.attempt',
            'password' => 'VoteTrustDemo!2026',
        ])->assertOk()
            ->assertJsonPath('state.authentication.totalAttempts', 4)
            ->assertJsonPath('state.authentication.authenticated', true)
            ->assertJsonPath('trace.2', 'auth-login-success');
    }

    public function test_live_demo_logout_and_replay_actions_are_processed_by_the_server(): void
    {
        config()->set('votetrust-user.security_demo.enabled', true);
        $this->actingAs(User::factory()->create());

        $this->postJson(route('security.demo.action'), ['action' => 'session.logout'])
            ->assertOk()
            ->assertJsonPath('state.session.tokenValid', false)
            ->assertJsonPath('trace.3', 'session-regenerate-csrf');
        $this->postJson(route('security.demo.action'), ['action' => 'session.replay'])
            ->assertOk()
            ->assertJsonPath('state.session.replayResult', 'Access denied')
            ->assertJsonPath('trace.1', 'session-replay-denied');

        $this->postJson(route('security.demo.action'), [
            'action' => 'mode.change',
            'mode' => 'vulnerable',
        ])->assertOk();
        $this->postJson(route('security.demo.action'), ['action' => 'session.logout'])
            ->assertOk()
            ->assertJsonPath('state.session.tokenValid', true)
            ->assertJsonPath('trace.1', 'session-redirect-only');
        $this->postJson(route('security.demo.action'), ['action' => 'session.replay'])
            ->assertOk()
            ->assertJsonPath('state.session.replayResult', 'Access granted')
            ->assertJsonPath('trace.1', 'session-replay-granted');
    }

    public function test_security_practice_demo_still_requires_authentication(): void
    {
        config()->set('votetrust-user.security_demo.enabled', true);

        $this->get(route('security.demo'))
            ->assertRedirect(route('login'));
    }

    public function test_security_practice_demo_cannot_be_enabled_in_production(): void
    {
        config()->set('votetrust-user.security_demo.enabled', true);
        $this->app->instance('env', 'production');

        $this->actingAs(User::factory()->create())
            ->get(route('security.demo'))
            ->assertNotFound();
    }
}
