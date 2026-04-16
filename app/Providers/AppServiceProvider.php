<?php

namespace App\Providers;

use App\Models\Session;
use App\Observers\BookingObserver;
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
        // Wire BookingObserver to Session (Booking) model events.
        // Requirements: 3.2 (recommendation cache invalidation), 6.1 (conflict evaluation)
        Session::observe(BookingObserver::class);
    }
}
