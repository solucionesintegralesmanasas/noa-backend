<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\UserAuthenticated;
use App\Listeners\HandlePostAuthentication;
use Illuminate\Support\Facades\Event;
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
        Event::listen(
            UserAuthenticated::class,
            HandlePostAuthentication::class
        );
    }
}
