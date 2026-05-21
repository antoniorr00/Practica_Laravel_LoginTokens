<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTokenIsValid;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Registramos las rutas API con prefijo /api y middleware 'api'.
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias del middleware propio para poder usarlo en routes/api.php
        // como Route::middleware('auth.token'). Es nuestra implementación,
        // NO es el middleware integrado en Sanctum (auth:sanctum).
        $middleware->alias([
            'auth.token' => EnsureTokenIsValid::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
