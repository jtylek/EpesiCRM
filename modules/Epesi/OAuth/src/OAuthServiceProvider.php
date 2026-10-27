<?php

namespace Epesi\Modules\OAuth;

use Epesi\Modules\OAuth\Console\RefreshTokensCommand;
use Epesi\Modules\OAuth\Models\OAuthClient;
use Epesi\Modules\OAuth\Models\OAuthToken;
use Epesi\Modules\OAuth\Providers\GoogleProvider;
use Epesi\Modules\OAuth\Providers\MicrosoftProvider;
use Epesi\Modules\OAuth\Services\OAuthFlow;
use Epesi\Modules\OAuth\Services\OAuthTokens;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * The shared OAuth2 layer (README.md, AI-shared/OAuth2-plan.md). A core
 * module: Mail's XOAUTH2 accounts are built on it, and Premium integrations
 * (Allegro) add their own providers to it.
 */
class OAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Relation::morphMap([
            'oauth_client' => OAuthClient::class,
            'oauth_token' => OAuthToken::class,
        ]);

        $this->mergeConfigFrom(__DIR__.'/../config/epesi-oauth.php', 'epesi-oauth');

        $this->app->singleton(OAuthTokens::class);
        $this->app->singleton(OAuthFlow::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        OAuthProviders::register(MicrosoftProvider::class);
        OAuthProviders::register(GoogleProvider::class);

        // Outside runningInConsole(): the cron URL and "Run now" run it in a web request.
        $this->commands([RefreshTokensCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('oauth:refresh')
                ->everyFifteenMinutes()
                ->withoutOverlapping();
        });
    }
}
