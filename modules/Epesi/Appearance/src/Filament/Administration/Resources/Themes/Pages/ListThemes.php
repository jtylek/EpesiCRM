<?php

namespace Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\Pages;

use Epesi\Modules\Appearance\Filament\Administration\Resources\Themes\ThemeResource;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListThemes extends ListRecords
{
    protected static string $resource = ThemeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
