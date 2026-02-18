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
        // Mantido ativo para compatibilidade com limites de índice do ambiente atual
        // (evita "Specified key was too long" com utf8mb4 em chaves primárias/índices).
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
