<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([

            'role' => \App\Http\Middleware\EnsureUserRole::class,

            'force.password' => \App\Http\Middleware\ForcePasswordChange::class,

            'active.user' => \App\Http\Middleware\EnsureUserIsActive::class,

        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $exception, $request) {
            if ($request->is('logout')) {
                return redirect()->route('login');
            }
        });
    })
    ->create();