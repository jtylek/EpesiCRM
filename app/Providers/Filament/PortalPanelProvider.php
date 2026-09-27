<?php

namespace App\Providers\Filament;

use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Portal\Pages\MyContact;
use App\Http\Middleware\DisabledInDemo;
use App\Http\Middleware\RedirectToSetup;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLoginAudit;
use App\Providers\Filament\Concerns\HasAuthBrandingStyles;
use Filament\Http\Middleware\Authenticate;
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
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The beginning of a customer portal (see AI-shared/Customer-portal.md): a
 * contact linked to a login with the 'customer' role — made in Administration
 * → Users, same as any other login — signs in here to nothing but their own
 * contact (MyContact, this panel's only page, so it's also the panel's home
 * page — see About's docblock in AdministrationPanelProvider for the same
 * "first/only page in ->pages() is the landing page" convention). Separate
 * from the main CRM panel on purpose: every other main-panel page/resource is
 * staff-only by its own Policy or canAccess(), and keeping the customer here
 * means none of them has to be re-checked for a role that was never meant to
 * reach them.
 *
 * Access is gated in User::canAccessPanel() ('portal' → hasRole('customer')).
 * In demo mode it answers 404 (DisabledInDemo), like Administration: there is
 * no demo customer account for it to be meaningful for.
 */
class PortalPanelProvider extends PanelProvider
{
    use HasAuthBrandingStyles;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->login()
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->brandName(fn (): string => __('epesi customer portal'))
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Neutral,
            ])
            // One page, nothing to navigate between.
            ->navigation(false)
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString($this->authBrandingStyles()),
            )
            ->pages([MyContact::class])
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
