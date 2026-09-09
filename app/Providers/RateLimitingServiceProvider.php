<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RateLimitingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Configura los limitadores de velocidad personalizados para la aplicación.
     */
    protected function configureRateLimiting(): void
    {
        //
    }
}
