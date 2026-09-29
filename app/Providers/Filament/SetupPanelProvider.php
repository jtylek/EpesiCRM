<?php

namespace App\Providers\Filament;

use App\Filament\Setup\Pages\FinishSetup;
use App\Filament\Setup\Pages\InstallWizard;
use App\Http\Middleware\DisabledInDemo;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The setup wizard (Epesi's FirstRun), at /setup. A panel of its own so it
 * gets Filament's look without the main panel's login, sidebar or module
 * plugins, none of which exist yet on a fresh install. Its pages close
 * themselves once setup is done. In demo mode it answers 404 (DisabledInDemo):
 * the demo is set up by `php artisan demo:reset`.
 */
class SetupPanelProvider extends PanelProvider
{
    /**
     * The wizard's step bar (Setup code, Server check, Database, Options,
     * Administrator, Mail, Install) has more steps than Filament's own
     * padding was sized for; without this, the last steps sit past the edge
     * of the card behind a horizontal scrollbar instead of all fitting.
     */
    protected function compactWizardHeaderStyles(): HtmlString
    {
        return new HtmlString(
            '<style>'
            .'.fi-simple-main .fi-sc-wizard-header-step-btn{column-gap:.5rem!important;padding-inline:.75rem!important;padding-block:.75rem!important}'
            .'.fi-simple-main .fi-sc-wizard-header-step-icon-ctn{width:1.75rem!important;height:1.75rem!important}'
            .'.fi-simple-main .fi-sc-wizard-header-step-separator{width:.75rem!important}'
            .'</style>',
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('setup')
            ->path('setup')
            ->brandName(fn (): string => __('epesi setup'))
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Neutral,
            ])
            ->viteTheme('resources/css/filament/epesi/theme.css')
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => $this->compactWizardHeaderStyles())
            // Not at the panel root: that is Filament's own "go to the first
            // page" route, which for a panel with no navigation leads back to
            // itself. routes/web.php sends /setup here instead.
            ->routes(function (): void {
                Route::get('/install', InstallWizard::class)->name('install');
                Route::get('/finish', FinishSetup::class)->name('finish');
            })
            ->middleware([
                DisabledInDemo::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent: Livewire's own requests need the language too.
            ->middleware([SetLocale::class], isPersistent: true);
    }
}
