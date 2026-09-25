<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectToDatabaseUpdate;
use App\Http\Middleware\RedirectToSetup;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLoginAudit;
use App\Providers\Filament\Concerns\HasBoxedFieldStyles;
use App\Providers\Filament\Concerns\HasCompactTableStyles;
use App\Providers\Filament\Concerns\HasSquareCardStyles;
use App\Support\Modules\ModuleRegistry;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * A personal-preferences panel, reached via the "Settings" item in the main
 * panel's user menu (see MainPanelProvider) rather than from the main
 * sidebar. Unlike the super_admin-only Administration panel, it's open to
 * every role — User::canAccessPanel() only special-cases 'administration' —
 * since its pages (Regional Settings, ...) hold per-user preferences rather
 * than admin tools.
 */
class UserSettingsPanelProvider extends PanelProvider
{
    use HasBoxedFieldStyles;
    use HasCompactTableStyles;
    use HasSquareCardStyles;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('user-settings')
            ->path('user-settings')
            ->login()
            ->brandName(fn (): string => __('epesi settings'))
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Neutral,
            ])
            ->sidebarWidth('16rem')
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString($this->compactTableStyles().$this->boxedFieldStyles().$this->squareCardStyles()),
            )
            ->plugins(ModuleRegistry::pluginsFor('user-settings'))
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
                RedirectToSetup::class,
                RedirectToDatabaseUpdate::class,
                TrackLoginAudit::class,
            ])
            // Persistent: Livewire's own requests need the language too.
            ->middleware([SetLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ])
            ->userMenuItems([
                Action::make('backToApp')
                    ->label('Back to App')
                    ->icon(Heroicon::OutlinedArrowLeft)
                    ->url(fn (): ?string => Filament::getPanel('main')->getUrl()),
            ]);
    }
}
