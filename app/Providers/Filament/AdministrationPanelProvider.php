<?php

namespace App\Providers\Filament;

use App\Filament\Administration\Pages\About;
use App\Filament\Administration\Pages\Cron;
use App\Filament\Administration\Pages\DatabaseUpdate;
use App\Filament\Administration\Pages\DemoDataPage;
use App\Filament\Administration\Pages\MailServer;
use App\Filament\Administration\Pages\Translations;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Support\UpdateNotice;
use App\Http\Middleware\DisabledInDemo;
use App\Http\Middleware\RedirectToSetup;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLoginAudit;
use App\Providers\Filament\Concerns\HasAuthBrandingStyles;
use App\Providers\Filament\Concerns\HasCompactTableStyles;
use App\Providers\Filament\Concerns\HasSmallCardCorners;
use App\Support\Modules\ModuleRegistry;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
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
 * Separate super_admin-only panel, reached via the "Administration" item in
 * the main panel's user menu (see MainPanelProvider, which opens the About
 * page first rather than Login Audit) rather than from the main sidebar —
 * access is gated in User::canAccessPanel(). Holds About, Login Audit,
 * Users, and filament-shield's Roles resource (moved here from the
 * main panel — see epesi-laravel-port memory, "Filament Shield moved into
 * the administration panel") — the natural home for admin-only tools that
 * shouldn't clutter the everyday CRM sidebar. In demo mode it answers 404
 * (DisabledInDemo).
 */
class AdministrationPanelProvider extends PanelProvider
{
    use HasAuthBrandingStyles;
    use HasCompactTableStyles;
    use HasSmallCardCorners;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('administration')
            ->path('administration')
            ->login()
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->brandName(fn (): string => __('epesi administration'))
            ->colors([
                'primary' => Color::Slate,
                'gray' => Color::Neutral,
            ])
            ->sidebarWidth('16rem')
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString($this->compactTableStyles().$this->smallCardCornerStyles().$this->authBrandingStyles()),
            )
            ->discoverResources(in: app_path('Filament/Administration/Resources'), for: 'App\Filament\Administration\Resources')
            ->pages([About::class, Cron::class, DatabaseUpdate::class, DemoDataPage::class, MailServer::class, Translations::class])
            ->renderHook(PanelsRenderHook::CONTENT_START, fn () => UpdateNotice::render())
            ->plugins([
                FilamentShieldPlugin::make(),
                ...ModuleRegistry::pluginsFor('administration'),
            ])
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
            ])
            ->userMenuItems([
                Action::make('backToApp')
                    ->label('Back to App')
                    ->icon(Heroicon::OutlinedArrowLeft)
                    ->url(fn (): ?string => Filament::getPanel('main')->getUrl()),
            ]);
    }
}
