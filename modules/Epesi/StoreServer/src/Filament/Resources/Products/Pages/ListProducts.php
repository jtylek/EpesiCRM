<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Products\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Epesi\Modules\StoreServer\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
