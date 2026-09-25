<?php

namespace App\Providers\Filament;

use App\Filament\Setup\Pages\FinishSetup;
use App\Filament\Setup\Pages\InstallWizard;
use App\Http\Middleware\SetLocale;
use App\Providers\Filament\Concerns\HasSquareCardStyles;
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
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The setup wizard (Epesi's FirstRun), at /setup. A panel of its own so it
 * gets Filament's look without the main panel's login, sidebar or module
 * plugins, none of which exist yet on a fresh install. Its pages close
 * themselves once setup is done.
 */
class SetupPanelProvider extends PanelProvider
{
    use HasSquareCardStyles;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('setup')
            ->path('setup')
            ->brandName('epesi')
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Neutral,
            ])
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => $this->squareCardStyles())
            // Not at the panel root: that is Filament's own "go to the first
            // page" route, which for a panel with no navigation leads back to
            // itself. routes/web.php sends /setup here instead.
            ->routes(function (): void {
                Route::get('/install', InstallWizard::class)->name('install');
                Route::get('/finish', FinishSetup::class)->name('finish');
            })
            ->middleware([
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
