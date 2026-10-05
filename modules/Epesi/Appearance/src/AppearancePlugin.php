<?php

namespace Epesi\Modules\Appearance;

use Epesi\Modules\Appearance\Filament\Pages\Appearance;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * What puts this module's screens into each panel — see CompaniesPlugin's
 * docblock for the general pattern.
 *
 * - "administration": under the Appearance menu group, the Themes resource,
 *   where an admin creates and edits named themes, and the Logo & Title page
 *   (titles, and the login pages' and customer portal's logos).
 * - "user-settings": the Appearance page, where a user picks one.
 *
 * Not "main": this module has no screen of its own there. The accent colour
 * and density it drives on every panel (including main) go through
 * App\Support\Appearance\CurrentTheme and App\Http\Middleware\ApplyThemeColor
 * instead of a plugin hook — those have to work whether or not this module
 * is even registered yet (see CurrentTheme's docblock), which a plugin hook,
 * only reachable once the module is attached, categorically can't.
 */
class AppearancePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'epesi-appearance';
    }

    public function register(Panel $panel): void
    {
        match ($panel->getId()) {
            'administration' => $panel
                ->discoverResources(
                    in: __DIR__.'/Filament/Administration/Resources',
                    for: 'Epesi\Modules\Appearance\Filament\Administration\Resources',
                )
                ->discoverPages(
                    in: __DIR__.'/Filament/Administration/Pages',
                    for: 'Epesi\Modules\Appearance\Filament\Administration\Pages',
                ),
            'user-settings' => $panel->pages([Appearance::class]),
            default => null,
        };
    }

    public function boot(Panel $panel): void {}
}
