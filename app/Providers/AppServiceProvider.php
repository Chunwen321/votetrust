<?php

namespace App\Providers;

use App\Contracts\Clock;
use App\Contracts\PaymentGateway;
use App\Contracts\RegistrationService;
use App\Contracts\VotingIntegration;
use App\Services\CandidateRegistration\RealRegistrationService;
use App\Services\CandidateRegistration\RegistrationProxy;
use App\Services\Clock\SystemClock;
use App\Services\Payment\HttpPaymentGateway;
use App\Services\Voting\ElectionVotingIntegration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
    }
}
