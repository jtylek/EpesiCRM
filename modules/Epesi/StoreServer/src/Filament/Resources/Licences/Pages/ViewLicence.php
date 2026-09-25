<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Licences\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Epesi\Modules\StoreServer\Filament\Resources\Licences\LicenceResource;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ViewLicence extends ViewRecord
{
    protected static string $resource = LicenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
        ];
    }
}
