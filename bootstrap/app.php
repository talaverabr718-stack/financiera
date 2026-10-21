<?php

use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Coolify terminates TLS at its reverse proxy. Trust its forwarded
        // headers so Laravel generates HTTPS asset, route, and redirect URLs.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['module' => EnsureModuleAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
