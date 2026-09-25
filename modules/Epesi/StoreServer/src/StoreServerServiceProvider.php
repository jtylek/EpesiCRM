<?php

namespace Epesi\Modules\StoreServer;

use Illuminate\Support\ServiceProvider;

/**
 * Routes are registered here rather than in the app's routes/web.php: a module
 * owns its own public surface, so installing or disabling the store server adds
 * or removes the API with no core edit. This app configures no `api:` routing at
 * all (see bootstrap/app.php), which is why the group below declares its own
 * prefix and middleware instead of joining an existing one.
 */
class StoreServerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}
