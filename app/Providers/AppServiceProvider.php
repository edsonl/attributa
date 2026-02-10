<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
//use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;

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
        Schema::defaultStringLength(191);

        Vite::useBuildDirectory('dist');

        //if (app()->environment('local')) {
        //    URL::forceScheme('https');
        //}

        RateLimiter::for('tracking', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->ip() ?? 'unknown'
            );
        });

    }
}
