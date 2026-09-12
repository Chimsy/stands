<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Throttle sign-in attempts by account and origin together: keying on the
     * address alone would punish everyone behind one office connection, and
     * keying on the account alone would let anyone lock out a known address.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip())
        ));

        /**
         * The readiness and diagnostics checks each cost a few round trips, so
         * they are capped per address. Liveness is deliberately not throttled:
         * it reads nothing, and a monitor must be able to tell "the application
         * is down" from "you polled too often".
         */
        RateLimiter::for('health', function (Request $request) {
            $perMinute = (int) config('health.rate_limit');

            return $perMinute > 0 ? Limit::perMinute($perMinute)->by($request->ip()) : Limit::none();
        });
    }
}
