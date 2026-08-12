<?php

// PIC: Chew Zhexuan

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Security\Authentication\DisableAccountAfterFailedAttemptsStrategy;
use App\Security\Authentication\LoginProtectionStrategy;
use App\Security\Session\FullSessionTerminationStrategy;
use App\Security\Session\SessionTerminationStrategy;
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
        self::assertSame(2, $strategy->allowChecks);
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
}
