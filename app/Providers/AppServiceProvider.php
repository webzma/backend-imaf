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
        RateLimiter::for('registro', fn (Request $request) => Limit::perMinutes(10, 5)
            ->by('registro|'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)
            ->by('login|'.strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('recuperacion', fn (Request $request) => Limit::perMinutes(15, 5)
            ->by('recuperacion|'.strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
