<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the local HTTPS proxy so X-Forwarded-Proto/Host are used for URL generation.
        // This prevents Swagger UI from generating http:// spec URLs that cause mixed-content errors.
        $middleware->trustProxies(at: '127.0.0.1');

        $middleware->statefulApi();
        $middleware->authenticateSessions();
    })
    ->withExceptions(function (): void {
        //
    })->create();

