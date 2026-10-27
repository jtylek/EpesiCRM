<?php

namespace App\Filament\Administration\Resources\Modules\Pages;

use App\Filament\Actions\InstallRoundcubeAction;
use App\Filament\Administration\Resources\Modules\ModuleActions;
use App\Filament\Administration\Resources\Modules\ModuleResource;
use App\Services\Setup\RoundcubeSetup;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class ListModules extends ListRecords
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstallRoundcubeAction::make()
                ->hidden(fn (): bool => app(RoundcubeSetup::class)->installed())
                ->color('gray'),
            Action::make('discover')
                ->label('Discover modules')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->color('gray')
                ->url(ModuleResource::getUrl('discover')),
            ModuleActions::install(),
        ];
    }
}
