<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Products\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Epesi\Modules\StoreServer\Filament\Resources\Products\ProductResource;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon(Heroicon::OutlinedPencil),
        ];
    }
}
