<?php

namespace Epesi\Modules\TaxRates\Filament\Resources\TaxRates\Pages;

use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;
use Epesi\Modules\TaxRates\Filament\Resources\TaxRates\TaxRateResource;

class EditTaxRate extends EditRecord
{
    protected static string $resource = TaxRateResource::class;
}
