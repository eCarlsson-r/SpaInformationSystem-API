<?php

namespace App\Providers;

use App\Models\Session;
use App\Observers\BookingObserver;
use App\Services\AITranslationService;
use App\Services\AITranslationServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the translation service interface to the Laravel AI SDK-backed implementation
        $this->app->bind(AITranslationServiceInterface::class, AITranslationService::class);
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
