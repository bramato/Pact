<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(using: function (): void {
        // These public, read-only routes deliberately have no session middleware.
        Route::middleware('cache.headers:public;max_age=300;s_maxage=3600')
            ->group(base_path('routes/web.php'));
        Route::get('/up', fn () => response('OK', 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]));
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel's default exception handling logs to stderr.
    })
    ->create();
