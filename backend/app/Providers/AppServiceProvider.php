<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Applied once to the public read-only API (routes/api.php). The framework's
        // "api" group has no limiter of its own, so requests are not throttled twice.
        // (Administration sign-in limits: App\Support\AdminLoginThrottle, used by the Filament login.)
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // The contact form (the only public write): a few messages per visitor, against spam.
        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('contact-10m|'.$request->ip()),
            Limit::perDay(20)->by('contact-day|'.$request->ip()),
        ]);
    }
}
