<?php

namespace App\Filament\Administration\Resources\Modules\Pages;

use App\Filament\Administration\Resources\Modules\ModuleActions;
use App\Filament\Administration\Resources\Modules\ModuleResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;

class ViewModule extends ViewRecord
{
    protected static string $resource = ModuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ModuleActions::toggle(),
            ModuleActions::generatePermissions(),
            ModuleActions::uninstall(),
        ];
    }
}
