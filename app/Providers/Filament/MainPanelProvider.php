<?php

namespace App\Providers\Filament;

use App\Filament\Administration\Resources\LoginAudits\LoginAuditResource;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\RedirectToDatabaseUpdate;
use App\Http\Middleware\RedirectToSetup;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackLoginAudit;
use App\Providers\Filament\Concerns\HasBoxedFieldStyles;
use App\Providers\Filament\Concerns\HasCompactTableStyles;
use App\Providers\Filament\Concerns\HasSquareCardStyles;
use App\Support\Modules\ModuleRegistry;
use Epesi\Modules\CRM\Contacts\Filament\Resources\Contacts\ContactResource;
use Epesi\Modules\RegionalSettings\Filament\Pages\RegionalSettings;
use Filament\Actions\Action;
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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class MainPanelProvider extends PanelProvider
{
    use HasBoxedFieldStyles;
    use HasCompactTableStyles;
    use HasSquareCardStyles;

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('main')
            ->path('')
            ->login()
            ->brandName('epesi')
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Neutral,
            ])
            ->sidebarWidth('16rem')
            ->maxContentWidth(Width::Full)
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): HtmlString => new HtmlString($this->compactTableStyles().$this->boxedFieldStyles().$this->squareCardStyles()),
            )
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
                'profile' => fn (Action $action): Action => auth()->user()?->contact
                    ? $action->url(ContactResource::getUrl('view', ['record' => auth()->user()->contact]))
                    : $action,
                Action::make('settings')
                    ->label('Settings')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => RegionalSettings::getUrl(panel: 'user-settings')),
                Action::make('administration')
                    ->label('Administration')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn (): string => LoginAuditResource::getUrl(panel: 'administration'))
                    ->visible(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false),
            ]);
    }
}
