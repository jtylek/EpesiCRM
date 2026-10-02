<?php

use App\Http\Middleware\RedirectToSetup;
use App\Routing\Router;
use App\Support\Optimize\FrameworkCaches;
use App\Support\Optimize\RelativeExpiryMemcachedStore;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
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

        // Cron decides for itself: a task marked evenInMaintenanceMode() (the
        // demo reset) still runs, every other one waits.
        $middleware->preventRequestsDuringMaintenance(except: ['cron']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Cached routes that still find the home page of an installation in a folder
// (App\Routing\CompiledRouteCollection). Nothing has resolved the router yet,
// so this one is the only one.
$app->singleton('router', fn (Application $app): Router => new Router($app['events'], $app));

// Memcached entries that expire on the server's clock or not at all, never
// on PHP's (App\Support\Optimize\RelativeExpiryMemcachedStore). Not a static
// closure: extend() binds it to the cache manager.
$app->afterResolving('cache', function (CacheManager $cache): void {
    $cache->extend('memcached', function (Application $app, array $config) {
        return $this->repository(new RelativeExpiryMemcachedStore(
            $app['memcached.connector']->connect(
                $config['servers'],
                $config['persistent_id'] ?? null,
                $config['options'] ?? [],
                array_filter($config['sasl'] ?? []),
            ),
            $this->getPrefix($config),
        ), $config);
    });
});

// Before the configuration loads: drops cached config and routes that .env or
// an unpacked release made stale (App\Support\Optimize\FrameworkCaches).
FrameworkCaches::guard($app);

return $app;
