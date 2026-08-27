<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Observers\PaymentObserver;
use App\Observers\PaymentReversalObserver;
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
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        Payment::observe(PaymentObserver::class);
        PaymentReversal::observe(PaymentReversalObserver::class);
    }
}
