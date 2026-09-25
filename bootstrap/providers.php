<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdministrationPanelProvider;
use App\Providers\Filament\MainPanelProvider;
use App\Providers\Filament\SetupPanelProvider;
use App\Providers\Filament\UserSettingsPanelProvider;
use App\Providers\ModuleServiceProvider;

return [
    // First: installed modules must be autoloadable before the panel providers
    // below configure their panels. See ModuleServiceProvider's own comment.
    ModuleServiceProvider::class,
    AppServiceProvider::class,
    MainPanelProvider::class,
    UserSettingsPanelProvider::class,
    AdministrationPanelProvider::class,
    SetupPanelProvider::class,
];
