<?php

namespace App\Providers\Filament;

use App\Filament\Auth\PanelLogin;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Portal\Resources\PortalContactResource;
use App\Http\Middleware\ApplyThemeColor;
use App\Http\Middleware\DisabledInDemo;
use App\Http\Middleware\RedirectToSetup;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLoginAudit;
use App\Support\Appearance\AppName;
use App\Support\Appearance\CurrentTheme;
use App\Support\Modules\ModuleRegistry;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The dealer portal (see AI-shared/features/access/dealer-portal.md): people at a dealer — a
 * company that sells or services your products — sign in with a login whose
 * role is `dealer` and whose contact belongs to that company. Built on the
 * customer portal's pattern (PortalPanelProvider): its own panel, so none of
 * the main panel's staff pages has to be re-checked for a role that was never
 * meant to reach them.
 *
 * A separate panel from the customer portal, not the same one with a second
 * role: what a dealer sees comes from modules (Equipment's machines, later
 * service jobs, orders and documents), which join this panel by listing
 * `dealer` in their module.json (ModuleRegistry::pluginsFor()). With its own
 * panel, the panel gate is the boundary — whatever joins it is for dealers
 * only — instead of every module resource having to keep customers out.
 *
 * The core part is only this: My Contact (the customer portal's page, the
 * dealer's own contact and sign-in addresses) and the module resources.
 * Access is gated in User::canAccessPanel() ('dealer' → hasRole('dealer')).
 * In demo mode it answers 404 (DisabledInDemo), like the customer portal.
 */
class DealerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('dealer')
            ->path('dealer')
            ->login(PanelLogin::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->brandName(fn (): string => AppName::dealer())
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Neutral,
            ])
            ->viteTheme('resources/css/filament/epesi/theme.css')
            // The main panel's layout: the same sidebar width, hideable from the
            // top bar, and content that fills the screen (a machines table is
            // wide) instead of Filament's 7xl column.
            ->sidebarWidth('15rem')
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): View => view('filament.components.history-navigation'))
            // A machine's files open in the same preview as in the main panel.
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.components.file-preview-modal'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): Htmlable => CurrentTheme::appearanceScript(Auth::user()))
            ->resources([PortalContactResource::class])
            ->plugins(ModuleRegistry::pluginsFor('dealer'))
            ->middleware([
                DisabledInDemo::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ApplyThemeColor::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RedirectToSetup::class,
                TrackLoginAudit::class,
            ])
            // Persistent: Livewire's own requests need the language too.
            ->middleware([SetLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
