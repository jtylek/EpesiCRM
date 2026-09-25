<?php

namespace App\Filament\Administration\Resources\Modules\Pages;

use App\Filament\Actions\InstallRoundcubeAction;
use App\Filament\Administration\Resources\Modules\ModuleActions;
use App\Filament\Administration\Resources\Modules\ModuleResource;
use App\Services\Setup\RoundcubeSetup;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;

class ListModules extends ListRecords
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InstallRoundcubeAction::make()
                ->hidden(fn (): bool => app(RoundcubeSetup::class)->installed())
                ->color('gray'),
            ModuleActions::install(),
        ];
    }
}
