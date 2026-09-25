<?php

use App\Http\Middleware\RedirectToSetup;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
        // Before authentication, so a fresh install goes straight to the
        // setup wizard rather than to a login page nobody can use yet.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: RedirectToSetup::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
