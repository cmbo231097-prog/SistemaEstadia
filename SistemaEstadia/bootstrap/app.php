<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Todas las pantallas se piden de nuevo al servidor para que los listados reflejen siempre la base de datos.
        $middleware->appendToGroup('web', \App\Http\Middleware\SinCacheNavegador::class);

        $middleware->alias([
            'rol' => \App\Http\Middleware\EnsureRole::class,
        ]);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
