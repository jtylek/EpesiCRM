<?php

namespace Epesi\Modules\TaxRates\Filament\Resources\TaxRates\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\CreateRecord;
use Epesi\Modules\TaxRates\Filament\Resources\TaxRates\TaxRateResource;

class CreateTaxRate extends CreateRecord
{
    protected static string $resource = TaxRateResource::class;
}
