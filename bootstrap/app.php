<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use App\Http\Middleware\HandleInertiaRequests;

return Application::configure(basePath: dirname(__DIR__))

    // ✅ ROUTES (SEM using)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    // ✅ RATE LIMITERS (LUGAR CORRETO)
    ->booted(function () {

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('tracking', function (Request $request) {
            return Limit::perMinute(600)->by($request->ip());
        });
    })

    // ✅ MIDDLEWARES
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->validateCsrfTokens(except: [
            'tracking/collect',
            'tracking/collect/*',
            'tracking/event',
            'tracking/event/*',
            'api/tracking/collect',
            'api/tracking/collect/*',
            'api/tracking/event',
            'api/tracking/event/*',
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        ]);

        $middleware->api(append: [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->alias([
            'auth'  => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })

    ->create();
