<?php

namespace Epesi\Modules\OAuth;

use Epesi\Modules\OAuth\Filament\Administration\Resources\OAuthApps\OAuthAppResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Administration → OAuth Apps. The connect buttons belong to the owners'
 * own pages (a mail account's form), which use ConnectOAuthAction.
 */
class OAuthPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-oauth';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() === 'administration') {
            $panel->resources([OAuthAppResource::class]);
        }
    }

    public function boot(Panel $panel): void {}
}
