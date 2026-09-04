<?php

namespace App\Providers;

use App\Contracts\Clock;
use App\Contracts\PaymentGateway;
use App\Contracts\PaymentGatewayContract;
use App\Contracts\RegistrationService;
use App\Contracts\VotingIntegration;
use App\Services\CandidateRegistration\RealRegistrationService;
use App\Services\CandidateRegistration\RegistrationProxy;
use App\Services\Clock\SystemClock;
use App\Services\Payment\HttpPaymentGateway;
use App\Services\Payment\IDontPay99Gateway;
use App\Services\Voting\ElectionVotingIntegration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PIC: Low Wen Kai — Election clock and payment integration bindings.
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(PaymentGateway::class, HttpPaymentGateway::class);

        // PIC: Risky Puah Kaho - Payment Module — pluggable gateway binding, IDontPay99 today, real provider later.
        $this->app->bind(PaymentGatewayContract::class, match (config('payment.gateway', 'mock')) {
            default => IDontPay99Gateway::class,
        });

        // PIC: Wong Shun Bin — Candidate registration Proxy binding.
        $this->app->when(RegistrationProxy::class)
            ->needs(RegistrationService::class)
            ->give(RealRegistrationService::class);
        $this->app->bind(RegistrationService::class, RegistrationProxy::class);

        // PIC: Goh Chun Wen — Voting Process module integration binding.
        $this->app->bind(VotingIntegration::class, ElectionVotingIntegration::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->assertLocalScreenshotEnvironment();

        // ngrok forwards the local virtual-host name to Apache. Keep generated
        // links on the public application URL instead of leaking that local host.
        $publicUrl = (string) config('app.url');
        $publicScheme = parse_url($publicUrl, PHP_URL_SCHEME);

        if ($publicUrl !== '' && is_string($publicScheme)) {
            URL::forceRootUrl($publicUrl);
            URL::forceScheme($publicScheme);
        }

        // PIC: Chew Zhexuan, Low Wen Kai, Wong Shun Bin, Goh Chun Wen - shared REST API throttle.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Limit repeated submissions without making different elections share one quota.
        RateLimiter::for('voting.cast', function (Request $request): Limit {
            $voter = $request->user()?->getAuthIdentifier() ?? $request->ip();
            $election = $request->route('election', 'unknown');

            return Limit::perMinute(max(1, (int) config('voting.cast_rate_limit_per_minute', 10)))
                ->by("voting:cast:{$voter}:{$election}");
        });
    }

    private function assertLocalScreenshotEnvironment(): void
    {
        $enabled = filter_var(env('SCREENSHOT_BEFORE_SECURITY', false), FILTER_VALIDATE_BOOL);
        $database = (string) config('database.connections.sqlite.database');
        $databaseRoot = (string) env('SCREENSHOT_DB_ROOT', '');
        $resolvedRoot = $databaseRoot !== '' ? realpath($databaseRoot) : false;
        $resolvedDirectory = $database !== '' ? realpath(dirname($database)) : false;

        if (! $enabled
            || ! $this->app->environment('local')
            || config('database.default') !== 'sqlite'
            || $resolvedRoot === false
            || $resolvedDirectory === false
            || ! str_starts_with(
                strtolower($resolvedDirectory.DIRECTORY_SEPARATOR),
                strtolower($resolvedRoot.DIRECTORY_SEPARATOR),
            )) {
            throw new RuntimeException('The insecure screenshot version may run only in the approved local SQLite environment.');
        }

        if (! $this->app->runningInConsole()) {
            $request = $this->app->make('request');
            if (! in_array($request->getHost(), ['127.0.0.1', 'localhost'], true)
                || ! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
                throw new RuntimeException('The insecure screenshot version accepts loopback requests only.');
            }
        }
    }
}
