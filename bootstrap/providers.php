<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdministrationPanelProvider;
use App\Providers\Filament\DealerPanelProvider;
use App\Providers\Filament\MainPanelProvider;
use App\Providers\Filament\PortalPanelProvider;
use App\Providers\Filament\SetupPanelProvider;
use App\Providers\Filament\UserSettingsPanelProvider;
use App\Providers\ModuleServiceProvider;

return [
    // First: installed modules must be autoloadable before the panel providers
    // below configure their panels. See ModuleServiceProvider's own comment.
    ModuleServiceProvider::class,
    // The Migration Wizard is registered by ModuleServiceProvider (from the modules
    // registry, or on a still-legacy site): listing it here is dropped by Laravel's
    // class_exists() filter, since its namespace is not autoloadable that early.
    AppServiceProvider::class,
    MainPanelProvider::class,
    UserSettingsPanelProvider::class,
    AdministrationPanelProvider::class,
    PortalPanelProvider::class,
    DealerPanelProvider::class,
    SetupPanelProvider::class,
];
