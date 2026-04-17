<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register ImageService as singleton
        $this->app->singleton(\App\Services\ImageService::class, function ($app) {
            return new \App\Services\ImageService('public');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
