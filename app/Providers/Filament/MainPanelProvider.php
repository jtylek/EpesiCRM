<?php

namespace App\Providers\Filament;

use App\Filament\Administration\Pages\About;
use App\Filament\Auth\Login;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Auth\ResetPassword;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\RedirectToDatabaseUpdate;
use App\Http\Middleware\RedirectToSetup;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLoginAudit;
use App\Providers\Filament\Concerns\HasAuthBrandingStyles;
use App\Providers\Filament\Concerns\HasBoxedFieldStyles;
use App\Providers\Filament\Concerns\HasCompactTableStyles;
use App\Providers\Filament\Concerns\HasSmallCardCorners;
use App\Support\Demo;
use App\Support\Modules\ModuleRegistry;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\RegionalSettings\Filament\Pages\RegionalSettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MainPanelProvider extends PanelProvider
{
    use HasAuthBrandingStyles;
    use HasBoxedFieldStyles;
    use HasCompactTableStyles;
    use HasSmallCardCorners;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('main')
            ->path('')
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->brandName('epesi')
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Neutral,
            ])
            ->sidebarWidth('13rem')
            // A topbar button (next to the logo) hides the sidebar entirely
            // and another (the hamburger, reused from mobile) brings it back
            // — Filament's own toggle, nothing custom. Not
            // sidebarCollapsibleOnDesktop(), which shrinks to an icon-only
            // rail rather than hiding it: that still spends width on every
            // page, where the point here is reclaiming it.
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString($this->compactTableStyles().$this->boxedFieldStyles().$this->smallCardCornerStyles().$this->authBrandingStyles()),
            )
            // A click swaps the page's content (Livewire's wire:navigate)
            // instead of loading a new page, so full screen lasts: a browser
            // leaves it on every page load. The other panels still load in
            // full, since SPA mode never removes a stylesheet and theirs
            // differ from this one's.
            ->spa()
            ->spaUrlExceptions(fn (): array => collect(Filament::getPanels())
                ->reject(fn (Panel $other): bool => $other->getId() === 'main')
                ->map(fn (Panel $other): string => url($other->getPath()).'*')
                ->values()
                ->all())
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): View => view('filament.components.history-navigation'))
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): View => view('filament.components.fullscreen-toggle'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.components.command-palette'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.components.link-copied-modal'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): View => view('filament.components.file-preview-modal'))
            // No ->discoverResources() for app/Filament/Resources: every CRM
            // recordset is now a module under modules/Epesi/CRM, and reaches
            // this panel through its plugin in the list below.
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->plugins(ModuleRegistry::pluginsFor('main'))
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
                Action::make('my-profile')
                    ->label('My Profile')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => ContactResource::getUrl('view', ['record' => auth()->user()->contact]))
                    ->visible(fn (): bool => auth()->user()?->contact !== null),
                Action::make('settings')
                    ->label('Settings')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => RegionalSettings::getUrl(panel: 'user-settings')),
                Action::make('administration')
                    ->label('Administration')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn (): string => About::getUrl(panel: 'administration'))
                    ->visible(fn (): bool => ! Demo::enabled() && (auth()->user()?->hasRole('super_admin') ?? false)),
            ]);
    }
}
