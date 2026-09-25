<?php

namespace Epesi\Modules\Roundcube;

use Epesi\Modules\Roundcube\Console\InstallRoundcubeCommand;
use Epesi\Modules\Roundcube\Listeners\EndRoundcubeSession;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class RoundcubeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/epesi-roundcube.php', 'epesi-roundcube');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-roundcube');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallRoundcubeCommand::class]);
        }

        // Roundcube writes this cookie, not Laravel, so decrypting it would
        // fail and hand EndRoundcubeSession an empty value.
        EncryptCookies::except([Roundcube::SESSION_COOKIE]);

        Event::listen(Logout::class, EndRoundcubeSession::class);
    }
}
