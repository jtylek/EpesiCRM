<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Licences\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\StoreServer\Filament\Resources\Licences\LicenceResource;
use Filament\Actions\CreateAction;

class ListLicences extends ListRecords
{
    protected static string $resource = LicenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
