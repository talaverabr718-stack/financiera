<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Observers\PaymentObserver;
use App\Observers\PaymentReversalObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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
        Vite::useHotFile(storage_path('framework/vite.hot'));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by('login:'.$request->ip()));
        RateLimiter::for('password-recovery', fn (Request $request) => Limit::perMinute(3)->by('password-recovery:'.$request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by('password-reset:'.$request->ip()));

        Payment::observe(PaymentObserver::class);
        PaymentReversal::observe(PaymentReversalObserver::class);
    }
}
