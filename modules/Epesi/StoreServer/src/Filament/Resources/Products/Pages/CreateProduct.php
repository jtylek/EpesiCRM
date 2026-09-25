<?php

namespace Epesi\Modules\StoreServer\Filament\Resources\Products\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Epesi\Modules\StoreServer\Filament\Resources\Products\ProductResource;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
